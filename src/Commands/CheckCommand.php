<?php

namespace g4t\Printly\Commands;

use g4t\Printly\PrintlyManager;
use Illuminate\Console\Command;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'printly:check {path? : Where to write the sample PDF (default: storage/app/printly-check.pdf)}';

    protected $description = 'Verify the PDF driver works by rendering a sample Arabic/English document';

    public function handle(PrintlyManager $printly): int
    {
        $path = $this->argument('path') ?? storage_path('app/printly-check.pdf');

        try {
            $printly->make()
                ->rtl()
                ->lang('ar')
                ->accent('#0f766e')
                ->title('g4t/printly')
                ->heading('مرحباً بالعالم')
                ->text('هذا ملف تجريبي للتأكد من أن اللغة العربية تظهر بحروف متصلة ومن اليمين إلى اليسار.')
                ->text('Mixed text works too: Laravel 12 مع PDF عربي.', color: '#b91c1c')
                ->table(['المنتج', 'الكمية', 'السعر'], [['قلم', 3, '1,500 د.ع'], ['دفتر', 1, '2,000 د.ع']])
                ->pageNumbers('صفحة {page} من {pages}')
                ->save($path);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("PDF written to [{$path}].");

        return self::SUCCESS;
    }
}
