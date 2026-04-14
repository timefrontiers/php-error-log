<?php

declare(strict_types=1);

namespace TimeFrontiers;

final class ErrorLog {
  public const SEGMENT_SPLIT = "/>";
  public const VALUE_ASSIGNMENT = ":>";
  public const LINE_BREAK = "\\>";

  protected string $_file = '';
  protected int $_total_lines = 0;
  protected int $_current_page = 1;
  protected int $_lines_limit = 25;
  protected int $_total_pages = 0;

  /**
   * @param string|null $base_dir Base directory for logs. If null, uses PRJ_ROOT or getcwd().
   * @param string $process_name Process name for filename (e.g., 'api'). If empty, uses date.
   * @param bool $is_error_log If true, logs go under 'errors/' subfolder; else 'logs/'.
   */
  public function __construct(
    ?string $base_dir = null,
    string $process_name = '',
    bool $is_error_log = true
  ) {
    $this->_file = $this->_buildFilePath($base_dir, $process_name, $is_error_log);
  }

  /**
   * Writes an error entry to the log file.
   *
   * @param ResponseStatus|string $status Status code prefix (e.g., ResponseStatus::UNKNOWN_ERROR or '4.').
   * @param string $message Brief description.
   * @param string|array $errors Error details (string or array of strings).
   * @return bool True on success.
   */
  public function write(
    ResponseStatus|string $status,
    string $message,
    string|array $errors = []
  ): bool {
    // Determine status prefix and error count
    $prefix = $status instanceof ResponseStatus ? $status->value : $status;
    $error_array = \is_array($errors) ? $errors : ($errors === '' ? [] : [$errors]);
    $error_count = \count($error_array);
    $full_status = $prefix . $error_count;

    // Sanitize message: remove newlines and delimiters to avoid breaking format
    $message = \str_replace(["\r\n", "\n", "\r"], ' ', $message);
    $message = \str_replace(
      [self::SEGMENT_SPLIT, self::VALUE_ASSIGNMENT, self::LINE_BREAK],
      [' ', ' ', ' '],
      $message
    );

    // Sanitize errors: replace newlines with LINE_BREAK for proper reconstruction
    $sanitized_errors = [];
    foreach ($error_array as $err) {
      $err = \str_replace(["\r\n", "\n", "\r"], self::LINE_BREAK, $err);
      // Also remove segment/value delimiters to avoid accidental parsing issues
      $err = \str_replace(
        [self::SEGMENT_SPLIT, self::VALUE_ASSIGNMENT],
        [' ', ' '],
        $err
      );
      $sanitized_errors[] = $err;
    }

    // Build line
    $line = 'Date' . self::VALUE_ASSIGNMENT . \date('Y-m-d H:i:s');
    $line .= self::SEGMENT_SPLIT . 'Status' . self::VALUE_ASSIGNMENT . $full_status;
    $line .= self::SEGMENT_SPLIT . 'Message' . self::VALUE_ASSIGNMENT . $message;
    if (!empty($sanitized_errors)) {
      $errors_str = \implode(self::LINE_BREAK, $sanitized_errors);
      $line .= self::SEGMENT_SPLIT . 'Errors' . self::VALUE_ASSIGNMENT . $errors_str;
    }

    // Ensure directory exists
    $dir = \dirname($this->_file);
    if (!\is_dir($dir) && !\mkdir($dir, 0755, true)) {
      return false;
    }

    // Append line with newline
    return (bool)\file_put_contents($this->_file, $line . \PHP_EOL, \FILE_APPEND | \LOCK_EX);
  }

  /**
   * Sets the file to be read. If not called, uses the file from constructor.
   *
   * @param string $file_path Full path to log file.
   */
  public function setFile(string $file_path): void  {
    $this->_file = $file_path;
    $this->_resetPagination();
  }

  /**
   * Reads log entries from the file with pagination.
   *
   * @param string|null $file_path Optional file path override.
   * @param int $page Page number (1-indexed).
   * @param int $limit Number of entries per page.
   * @return array Array of parsed entries for the requested page.
   */
  public function read(
    ?string $file_path = null,
    int $page = 1,
    int $limit = 25
  ): array {
    if ($file_path !== null) {
      $this->setFile($file_path);
    }

    if ($this->_file === '' || !\file_exists($this->_file)) {
      return [];
    }

    $this->_lines_limit = \max(1, $limit);
    $this->_current_page = \max(1, $page);

    // Count total lines efficiently
    $this->_total_lines = $this->_countLines($this->_file);
    $this->_total_pages = (int)\ceil($this->_total_lines / $this->_lines_limit);
    $this->_current_page = \min($this->_current_page, \max(1, $this->_total_pages));

    $offset = ($this->_current_page - 1) * $this->_lines_limit;
    $lines = $this->_readLinesSlice($this->_file, $offset, $this->_lines_limit);

    $entries = [];
    foreach ($lines as $line) {
      $entry = $this->_parseLine($line);
      if ($entry !== null) {
        $entries[] = $entry;
      }
    }

    return $entries;
  }

  // -------------------------------------------------------------------------
  // Pagination Helpers
  // -------------------------------------------------------------------------

  public function hasNextPage(): bool {
    return $this->_current_page < $this->_total_pages;
  }

  public function hasPreviousPage(): bool {
    return $this->_current_page > 1;
  }

  public function getNextPage(): int {
    return $this->hasNextPage() ? $this->_current_page + 1 : $this->_current_page;
  }

  public function getPreviousPage(): int {
    return $this->hasPreviousPage() ? $this->_current_page - 1 : 1;
  }

  public function getTotalPages(): int {
    return $this->_total_pages;
  }

  public function getCurrentPage(): int {
    return $this->_current_page;
  }

  public function getTotalLines(): int {
    return $this->_total_lines;
  }

  // -------------------------------------------------------------------------
  // Private Helpers
  // -------------------------------------------------------------------------

  /**
   * Builds the full log file path.
   */
  private function _buildFilePath(?string $base_dir, string $process_name, bool $is_error_log): string {
    if ($base_dir === null) {
      $base_dir = \defined('PRJ_ROOT') ? \constant('PRJ_ROOT') : \getcwd();
    }
    $base_dir = \rtrim(\str_replace('\\', '/', $base_dir), '/');

    $subfolder = $is_error_log ? 'errors' : 'logs';
    $date_path = \date('Y-m'); // Year-Month

    $dir = "{$base_dir}/{$subfolder}/{$date_path}";

    $filename = $process_name !== '' ? "{$process_name}.log" : \date('Y-m-d') . '.log';

    return "{$dir}/{$filename}";
  }

  /**
   * Counts lines in a file without loading all content.
   */
  private function _countLines(string $file): int {
    $handle = \fopen($file, 'rb');
    if (!$handle) {
      return 0;
    }
    $count = 0;
    while (!\feof($handle)) {
      $buffer = \fread($handle, 8192);
      $count += \substr_count($buffer, "\n");
    }
    \fclose($handle);
    return $count;
  }

  /**
   * Reads a slice of lines from a file.
   *
   * @return string[] Array of lines (without trailing newline).
   */
  private function _readLinesSlice(string $file, int $offset, int $limit): array {
    $lines = [];
    $handle = \fopen($file, 'rb');
    if (!$handle) {
      return $lines;
    }

    $current_line = 0;
    while (!\feof($handle) && \count($lines) < $limit) {
      $line = \fgets($handle);
      if ($line === false) {
        break;
      }
      if ($current_line >= $offset) {
        $lines[] = \rtrim($line, "\r\n");
      }
      $current_line++;
    }
    \fclose($handle);
    return $lines;
  }

  /**
   * Parses a single log line into an associative array.
   */
  private function _parseLine(string $line): ?array {
    $segments = \explode(self::SEGMENT_SPLIT, $line);
    $data = [];
    foreach ($segments as $seg) {
      $parts = \explode(self::VALUE_ASSIGNMENT, $seg, 2);
      if (\count($parts) === 2) {
        $data[\trim($parts[0])] = \trim($parts[1]);
      }
    }

    if (!isset($data['Status'], $data['Message'])) {
      return null; // Invalid line
    }

    $errors = [];
    if (isset($data['Errors'])) {
      $errors = \explode(self::LINE_BREAK, $data['Errors']);
    }

    return [
      'status'  => $data['Status'],
      'date'    => $data['Date'] ?? '',
      'subject' => $data['Message'],
      'content' => $errors,
    ];
  }

  /**
   * Resets pagination state.
   */
  private function _resetPagination(): void {
    $this->_total_lines = 0;
    $this->_current_page = 1;
    $this->_total_pages = 0;
  }
}