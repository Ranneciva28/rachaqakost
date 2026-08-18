<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImageLedgerExtractor
{
    public function extract(array $files, int $defaultYear, string $ledgerKind): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY belum dipasang di Railway Variables.');
        }

        $temporaryFiles = [];
        try {
            $content = [[
                'type'=>'input_text',
                'text'=>$this->prompt($defaultYear, $ledgerKind),
            ]];
            foreach ($files as $file) {
                [$mime, $bytes, $temporary] = $this->readImage($file);
                if ($temporary) $temporaryFiles[] = $temporary;
                $content[] = [
                    'type'=>'input_image',
                    'image_url'=>'data:'.$mime.';base64,'.base64_encode($bytes),
                    'detail'=>'high',
                ];
            }

            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(180)
                ->retry(2, 1200, throw: false)
                ->post('https://api.openai.com/v1/responses', [
                    'model'=>config('services.openai.vision_model', 'gpt-5.4'),
                    'store'=>false,
                    'input'=>[[
                        'role'=>'user',
                        'content'=>$content,
                    ]],
                    'text'=>['format'=>[
                        'type'=>'json_schema',
                        'name'=>'ledger_book_extraction',
                        'strict'=>true,
                        'schema'=>$this->schema(),
                    ]],
                ]);

            if (! $response->successful()) {
                $message = $response->json('error.message') ?: 'Vision API gagal memproses gambar.';
                throw new RuntimeException($message);
            }

            $text = $this->outputText($response->json());
            $decoded = json_decode($text, true);
            if (! is_array($decoded) || ! isset($decoded['rows']) || ! is_array($decoded['rows'])) {
                throw new RuntimeException('Vision API tidak mengembalikan format transaksi yang valid.');
            }

            return $decoded['rows'];
        } finally {
            foreach ($temporaryFiles as $path) @unlink($path);
        }
    }

    private function readImage(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, ['image/heic', 'image/heif'], true)) {
            return [$mime ?: 'image/jpeg', file_get_contents($file->getRealPath()), null];
        }

        $target = sys_get_temp_dir().'/rachaqakost-'.bin2hex(random_bytes(8)).'.jpg';
        $command = 'heif-convert '.escapeshellarg($file->getRealPath()).' '.escapeshellarg($target).' 2>&1';
        exec($command, $output, $status);
        if ($status !== 0 || ! is_file($target)) {
            throw new RuntimeException('Foto HEIC gagal dikonversi. Ubah format kamera ke JPEG atau unggah screenshot.');
        }

        return ['image/jpeg', file_get_contents($target), $target];
    }

    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $output) {
            if (($output['type'] ?? null) !== 'message') continue;
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) return $content['text'];
            }
        }
        throw new RuntimeException('Vision API tidak menghasilkan teks keluaran.');
    }

    private function prompt(int $year, string $kind): string
    {
        $scope = match ($kind) {
            'PAYMENT' => 'Hanya ambil transaksi pendapatan atau pembayaran sewa.',
            'EXPENSE' => 'Hanya ambil transaksi pengeluaran.',
            'TENANT' => 'Hanya ambil riwayat penghuni yang sudah keluar. Gunakan jenis TENANT.',
            default => 'Ambil transaksi pendapatan/pembayaran, pengeluaran, dan riwayat penghuni checkout, lalu bedakan jenisnya.',
        };

        return "Baca seluruh foto buku pembukuan kos berbahasa Indonesia. {$scope}\n"
            ."Salin satu baris buku menjadi satu transaksi dan jangan mengarang nilai yang tidak terlihat. Tahun default adalah {$year} bila tulisan hanya memuat tanggal dan bulan. "
            .'Nominal harus berupa angka Rupiah tanpa separator. Siklus tagihan hanya DAILY, WEEKLY, atau MONTHLY. Metode hanya Transfer, Cash, atau QRIS. '
            .'Jika informasi tidak terbaca, isi null dan turunkan confidence. period_start adalah awal periode sewa yang dibayar, bukan tanggal pembayaran. '
            .'Untuk expense, isi title dan category jika tertulis. Untuk payment, isi tenant_name dan room_number jika tersedia. '
            .'Untuk riwayat penghuni, gunakan TENANT serta isi tenant_name, room_number, phone, identity_number, move_in, dan move_out dari buku. notes berisi teks penting lain dari baris tersebut.';
    }

    private function schema(): array
    {
        $nullableString = ['type'=>['string', 'null']];
        $nullableNumber = ['type'=>['number', 'null']];
        $nullableInteger = ['type'=>['integer', 'null']];

        return [
            'type'=>'object',
            'properties'=>[
                'rows'=>[
                    'type'=>'array',
                    'items'=>[
                        'type'=>'object',
                        'properties'=>[
                            'page_number'=>['type'=>'integer', 'minimum'=>1],
                            'row_number'=>['type'=>'integer', 'minimum'=>1],
                            'transaction_type'=>['type'=>'string', 'enum'=>['PAYMENT','EXPENSE','TENANT']],
                            'tenant_name'=>$nullableString,
                            'room_number'=>$nullableString,
                            'phone'=>$nullableString,
                            'identity_number'=>$nullableString,
                            'move_in'=>$nullableString,
                            'move_out'=>$nullableString,
                            'transaction_date'=>$nullableString,
                            'amount'=>$nullableNumber,
                            'billing_cycle'=>['type'=>['string','null'], 'enum'=>['DAILY','WEEKLY','MONTHLY',null]],
                            'period_count'=>$nullableInteger,
                            'period_start'=>$nullableString,
                            'method'=>['type'=>['string','null'], 'enum'=>['Transfer','Cash','QRIS',null]],
                            'category'=>$nullableString,
                            'title'=>$nullableString,
                            'notes'=>$nullableString,
                            'confidence'=>['type'=>'number', 'minimum'=>0, 'maximum'=>100],
                        ],
                        'required'=>['page_number','row_number','transaction_type','tenant_name','room_number','phone','identity_number','move_in','move_out','transaction_date','amount','billing_cycle','period_count','period_start','method','category','title','notes','confidence'],
                        'additionalProperties'=>false,
                    ],
                ],
            ],
            'required'=>['rows'],
            'additionalProperties'=>false,
        ];
    }
}
