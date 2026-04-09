<?php
// ... (require_once 'db_connect.php'; and other setup) ...
require_once 'config.php';
// --- SQL Query to fetch Response History Data ---
try {
    $sql = "
        SELECT 
            i.incident_ID AS 'Incident ID',
            CONCAT(u.first_name, ' ', u.last_name) AS 'User Name',
            u.role AS 'User Role',
            l.address AS 'Incident Address',
            i.incident_level AS 'Incident Level',
            i.incident_type AS 'Incident Type',
            i.status AS 'Final Status',
            -- Use DATE_FORMAT to output the timestamp as a readable string
            DATE_FORMAT(i.start_timestamp, '%Y-%m-%d %H:%i:%s') AS 'Date/Time Started',
            DATE_FORMAT(i.end_timestamp, '%Y-%m-%d %H:%i:%s') AS 'Date/Time Resolved',
            -- Calculation for response time remains the same
            TIMESTAMPDIFF(MINUTE, i.start_timestamp, i.end_timestamp) AS 'Response Time (Mins)'
        FROM 
            incident_log i
        JOIN 
            sensor s ON i.FK_sensor_ID = s.sensor_ID
        JOIN 
            location l ON s.FK_location_ID = l.location_ID
        JOIN 
            users u ON l.FK_user_ID = u.user_ID
        ORDER BY 
            i.start_timestamp DESC;
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        die("No incident data found to export.");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- CSV File Generation & Stream ---

// 1. Set headers for CSV download
// ... (headers as before) ...
$filename = "BFP_Response_Report_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// 2. Open a file pointer to the output stream
$output = fopen('php://output', 'w');

// 3. Output the CSV column headers 
$header = array_keys($results[0]);
fputcsv($output, $header);

// 4. Output the CSV data rows with Text Formatting Fix
foreach ($results as $row) {
    // Columns to force as text (using the column names from the SQL SELECT)
    $text_columns = ['Date/Time Started', 'Date/Time Resolved'];

    // Loop through the columns and prepend a single quote to force text in Excel
    foreach ($text_columns as $col_name) {
        if (isset($row[$col_name]) && $row[$col_name] !== null) {
            // Prepend the single quote to the string value
            $row[$col_name] = "'" . $row[$col_name]; 
        }
        // Also ensure Response Time (Mins) is treated as a number
        if ($col_name === 'Response Time (Mins)' && $row[$col_name] !== null) {
             // You can explicitly cast this to a string as well if needed, but it's a number
             // Excel is usually fine with numeric columns
        }
    }
    
    // Output the modified row to the CSV file
    fputcsv($output, $row);
}

// 5. Close the file pointer and stop execution
fclose($output);
exit;
?>