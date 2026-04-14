<?php

declare(strict_types=1);

namespace TimeFrontiers;

enum ResponseStatus: string {
  case NO_ERROR = '0.0';
  case NO_TASK = '0.1';
  case NO_DATA = '0.2';
  case ACCESS_ERROR = '1.';
  case INPUT_ERROR = '2.';
  case PROCESS_ERROR = '3.';
  case UNKNOWN_ERROR = '4.';
  case THIRD_PARTY_ERROR = '5.';
}