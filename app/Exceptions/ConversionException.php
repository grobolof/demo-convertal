<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class ConversionException extends RuntimeException implements ShouldntReport {}
