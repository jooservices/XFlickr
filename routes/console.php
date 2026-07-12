<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('xflickr:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('xflickr:spider:expand')->everyMinute()->withoutOverlapping();
Schedule::command('xflickr:contacts:full-pass-expand')->everyMinute()->withoutOverlapping();
