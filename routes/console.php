<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('xflickr:dispatch')->everyMinute()->withoutOverlapping();
