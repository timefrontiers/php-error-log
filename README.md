# TimeFrontiers PHP Error Log

A lightweight PHP logging utility that writes structured error entries and reads them back with pagination support.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Features

- **Structured log format** using custom delimiters for easy parsing.
- **Pagination** when reading large log files.
- **Status code prefixes** following TimeFrontiers API response pattern.
- **Zero dependencies** – only requires PHP 8.1+.
- **PSR‑4 autoloading**.

## Installation

```bash
composer require timefrontiers/php-error-log
```

## Requirements

- PHP 8.1 or higher

## Basic Usage

### Writing Logs

```php
use TimeFrontiers\ErrorLog;
use TimeFrontiers\ResponseStatus;

$log = new ErrorLog(
  base_dir: '/var/www/project',
  process_name: 'api',
  is_error_log: true
);

$log->write(
  ResponseStatus::UNKNOWN_ERROR,
  'Request failed.',
  ['Failed to populate order-items', 'SQL syntax error near ...']
);
```

### Reading Logs

```php
$log = new ErrorLog();

// Read first page
$entries = $log->read('/path/to/logfile.log', 1, 25);

// Navigate pages
while ($log->hasNextPage()) {
  $next_page = $log->getNextPage();
  $entries = $log->read(null, $next_page, 25);
  // Process entries...
}

echo "Total pages: " . $log->getTotalPages();
```

### ResponseStatus Enum

The package includes a `ResponseStatus` enum for consistent status codes:

| Case               | Value | Description                          |
|--------------------|-------|--------------------------------------|
| `NO_ERROR`         | 0.0   | Process completed successfully       |
| `NO_TASK`          | 0.1   | No action was taken                  |
| `NO_DATA`          | 0.2   | No data found                        |
| `ACCESS_ERROR`     | 1.    | Permission/access error              |
| `INPUT_ERROR`      | 2.    | Validation/malformed input error     |
| `PROCESS_ERROR`    | 3.    | System cannot process at this time   |
| `UNKNOWN_ERROR`    | 4.    | Uncategorized error                  |
| `THIRD_PARTY_ERROR`| 5.    | Third‑party API/gateway error        |

The decimal part (`.x`) is automatically appended based on the number of error strings provided.

## Log Format

Each entry is a single line:

```
Date:>2024-05-31 00:46:25/>Status:>4.2/>Message:>Request failed./>Errors:>First error\>Second error
```

- Segments separated by `/>`
- Key‑value pairs use `:>`
- Multiple errors separated by `\>`

## License

MIT License. See [LICENSE](LICENSE) for details.
```

---