#!/bin/bash
rm profiling_/cachegrind.out.*
php -d xdebug.profiler_enable=On whois.php
mv /global/php-profiling/cachegrind.out.* profiling_/

