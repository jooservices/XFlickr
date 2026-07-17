<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('xflickr:crawler:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('xflickr:spider:expand')->everyMinute()->withoutOverlapping();
Schedule::command('xflickr:contacts:full-pass-expand')->everyMinute()->withoutOverlapping();
Schedule::command('xflickr:transfer:integrity-scan')->dailyAt('02:00')->withoutOverlapping();
