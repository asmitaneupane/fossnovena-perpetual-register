<?php
/**
 * Handles CSV import for Perpetual Register
 *
 * @since      1.0.0
 * @package    Fossnovena_PR
 * @subpackage Fossnovena_PR/includes
 */

if ( ! defined('ABSPATH') ) exit;

class FNPR_Importer {

    /**
     * Import a CSV file.
     *
     * @param string $file_path Absolute path to CSV.
     * @param string $mode      'replace' or 'append'
     * @param bool   $ignore_duplicates If true, duplicates are skipped silently.
     * @return array ['inserted'=>int,'skipped'=>int,'replaced'=>bool,'errors'=>array]
     */
    public function import($file_path, $mode = 'append', $ignore_duplicates = true) {
        global $wpdb;
        $table = FNPR_TABLE;

        if (! file_exists($file_path)) {
            return ['inserted'=>0,'skipped'=>0,'replaced'=>false,'errors'=>['File not found']];
        }

        // Read lines safely (handle BOM & UTF-8)
        $handle = fopen($file_path, 'r');
        if (! $handle) return ['inserted'=>0,'skipped'=>0,'replaced'=>false,'errors'=>['Unable to open CSV']];

        // Detect delimiter by first line (fallback comma)
        $firstLine = fgets($handle);
        $delimiter = $this->detect_delimiter($firstLine);
        // Rewind to start
        fclose($handle);
        $handle = fopen($file_path, 'r');

        // Handle BOM
        $bom = pack('H*', 'EFBBBF');
        $first = fgets($handle);
        if (0 !== strncmp($first, $bom, 3)) {
            // Not BOM; rewind to start of file
            fclose($handle);
            $handle = fopen($file_path, 'r');
        }

        // Parse CSV
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            fclose($handle);
            return ['inserted'=>0,'skipped'=>0,'replaced'=>false,'errors'=>['Empty CSV']];
        }

        // Normalize header; if no header assume first col is name
        $has_header = $this->looks_like_header($header);
        if (!$has_header) {
            // Treat first row as data; set fake header
            $data_first_row = $header;
            $header = ['name'];
        }

        $name_idx = $this->name_index($header);

        $rows = [];
        if (!$has_header) {
            $rows[] = $this->sanitize_name($data_first_row[0] ?? '');
        }

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $name = $this->sanitize_name($row[$name_idx] ?? '');
            if ($name !== '') $rows[] = $name;
        }
        fclose($handle);

        // De-dup in memory to reduce DB chatter
        $rows = array_values(array_unique($rows, SORT_STRING));

        $inserted = 0; $skipped = 0; $errors = [];

        // Replace mode: truncate first
        $replaced = false;
        if ($mode === 'replace') {
            $wpdb->query("TRUNCATE TABLE $table");
            $replaced = true;

            // Also replace master CSV file
            $this->write_master_csv($rows);
        } else {
            // Append mode → append to master CSV
            $this->append_master_csv($rows);
        }

        // Bulk insert with prepared statements & IGNORE duplicates
        foreach ($rows as $name) {
            if ($name === '') { $skipped++; continue; }
            $sql = "INSERT " . ($ignore_duplicates ? "IGNORE" : "") . " INTO $table (full_name) VALUES (%s)";
            $prepared = $wpdb->prepare($sql, $name);
            $result = $wpdb->query($prepared);
            if ($result === false) {
                $errors[] = "DB error inserting: $name";
            } elseif ($result === 0) {
                $skipped++;
            } else {
                $inserted++;
            }
        }

        return compact('inserted','skipped','replaced','errors');
    }

    private function detect_delimiter($line) {
        $delims = [',',';','|','\t'];
        $best = ',';
        $maxCount = 0;
        foreach ($delims as $d) {
            $c = substr_count($line, $d);
            if ($c > $maxCount) { $maxCount = $c; $best = $d; }
        }
        return $best === '\t' ? "\t" : $best;
    }

    private function looks_like_header($header_row) {
        $h = array_map('strtolower', $header_row);
        return (bool) array_filter($h, function($v){ return strpos($v, 'name') !== false; });
    }

    private function name_index($header_row) {
        foreach ($header_row as $i => $col) {
            if (stripos($col, 'name') !== false) return $i;
        }
        return 0;
    }

    private function sanitize_name($name) {
        $name = trim($name);
        // collapse consecutive spaces
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    private function write_master_csv($names) {
        if (! file_exists(FNPR_UPLOAD_DIR)) wp_mkdir_p(FNPR_UPLOAD_DIR);
        $fh = fopen(FNPR_CSV_PATH, 'w');
        fputcsv($fh, ['name']);
        foreach ($names as $n) fputcsv($fh, [$n]);
        fclose($fh);
    }

    private function append_master_csv($names) {
        // Avoid writing duplicates to file by reading current names into a set
        $existing = [];
        if (file_exists(FNPR_CSV_PATH)) {
            if (($fh = fopen(FNPR_CSV_PATH, 'r')) !== false) {
                $header = fgetcsv($fh);
                while (($row = fgetcsv($fh)) !== false) {
                    $existing[$row[0]] = true;
                }
                fclose($fh);
            }
        } else {
            $this->write_master_csv([]);
        }
        $fh2 = fopen(FNPR_CSV_PATH, 'a');
        foreach ($names as $n) {
            if (!isset($existing[$n])) fputcsv($fh2, [$n]);
        }
        fclose($fh2);
    }
}
