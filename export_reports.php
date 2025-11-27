<?php
session_start();
require_once __DIR__.'/db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

// Categories configuration
$categories = [
    'ambulance' => ['label' => 'Ambulance', 'collection' => 'ambulance_reports', 'icon' => 'truck', 'color' => 'blue'],
    'tanod'     => ['label' => 'Tanod',     'collection' => 'tanod_reports',     'icon' => 'shield-check', 'color' => 'sky'],
    'fire'      => ['label' => 'Fire',      'collection' => 'fire_reports',      'icon' => 'fire', 'color' => 'red'],
    'flood'     => ['label' => 'Flood',     'collection' => 'flood_reports',     'icon' => 'home', 'color' => 'indigo'],
    'other'     => ['label' => 'Other',     'collection' => 'other_reports',     'icon' => 'question-mark-circle', 'color' => 'gray'],
];

/**
 * Get all reports from a collection
 */
function get_all_reports(string $collection): array {
    try {
        $url = firestore_base_url() . ':runQuery';
        $body = [
            'structuredQuery' => [
                'from' => [['collectionId' => $collection]],
                'orderBy' => [[
                    'field' => ['fieldPath' => 'timestamp'],
                    'direction' => 'DESCENDING',
                ]],
                'limit' => 1000, // Get up to 1000 reports
            ]
        ];
        
        $response = firestore_rest_request('POST', $url, $body);
        $reports = [];
        
        if (is_array($response)) {
            foreach ($response as $row) {
                if (isset($row['document'])) {
                    $doc = $row['document'];
                    $data = firestore_decode_fields($doc['fields'] ?? []);
                    $name = $doc['name'] ?? '';
                    $data['_id'] = $name ? basename($name) : '';
                    $data['_created'] = $doc['createTime'] ?? null;
                    $reports[] = $data;
                }
            }
        }
        
        return $reports;
    } catch (Exception $e) {
        error_log("Error fetching reports from {$collection}: " . $e->getMessage());
        return [];
    }
}

/**
 * Export to Excel (CSV format)
 */
function export_to_excel(array $allReports, array $categories): void {
    $filename = 'iBantay_Reports_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    fputcsv($output, [
        'Category',
        'Report ID',
        'Full Name',
        'Contact',
        'Location',
        'Purpose/Description',
        'Status',
        'Image URL',
        'Timestamp',
        'Created Date'
    ]);
    
    // Add data rows
    foreach ($allReports as $categorySlug => $reports) {
        $categoryLabel = $categories[$categorySlug]['label'] ?? $categorySlug;
        
        foreach ($reports as $report) {
            $timestamp = $report['timestamp'] ?? '';
            $createdDate = '';
            
            if ($timestamp) {
                if (is_string($timestamp)) {
                    $createdDate = date('Y-m-d H:i:s', strtotime($timestamp));
                } elseif (is_array($timestamp) && isset($timestamp['_seconds'])) {
                    $createdDate = date('Y-m-d H:i:s', $timestamp['_seconds']);
                }
            }
            
            // Get image URL if available
            $imageUrl = '';
            if (!empty($report['imageUrl'])) {
                $imageUrl = $report['imageUrl'];
            } elseif (!empty($report['image'])) {
                $imageUrl = $report['image'];
            }
            
            fputcsv($output, [
                $categoryLabel,
                $report['_id'] ?? '',
                $report['fullName'] ?? '',
                $report['contact'] ?? '',
                $report['location'] ?? '',
                $report['purpose'] ?? '',
                $report['status'] ?? '',
                $imageUrl,
                $timestamp,
                $createdDate
            ]);
        }
    }
    
    fclose($output);
    exit();
}

/**
 * Export to PDF
 */
function export_to_pdf(array $allReports, array $categories): void {
    // For PDF, we'll create a simple HTML that can be printed or converted
    $filename = 'iBantay_Reports_' . date('Y-m-d_H-i-s') . '.html';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManResponde Reports Export</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="responde.png">
    <link rel="icon" type="image/png" sizes="16x16" href="responde.png">
    <link rel="apple-touch-icon" href="responde.png">
    <link rel="shortcut icon" href="responde.png">
    
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .category { margin-bottom: 30px; page-break-inside: avoid; }
        .category h2 { color: #2563eb; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .status-approved { color: #059669; font-weight: bold; }
        .status-pending { color: #d97706; font-weight: bold; }
        .status-declined { color: #dc2626; font-weight: bold; }
        .summary { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
        @media print {
            .category { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ManResponde Reports Export</h1>
        <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
    </div>';
    
    // Summary statistics
    $totalReports = 0;
    $totalApproved = 0;
    $totalPending = 0;
    $totalDeclined = 0;
    
    foreach ($allReports as $reports) {
        $totalReports += count($reports);
        foreach ($reports as $report) {
            $status = strtolower($report['status'] ?? '');
            if ($status === 'approved') $totalApproved++;
            elseif ($status === 'pending') $totalPending++;
            elseif ($status === 'declined') $totalDeclined++;
        }
    }
    
    echo '<div class="summary">
        <h3>Summary</h3>
        <p><strong>Total Reports:</strong> ' . $totalReports . '</p>
        <p><strong>Approved:</strong> ' . $totalApproved . '</p>
        <p><strong>Pending:</strong> ' . $totalPending . '</p>
        <p><strong>Declined:</strong> ' . $totalDeclined . '</p>
    </div>';
    
    // Reports by category
    foreach ($allReports as $categorySlug => $reports) {
        if (empty($reports)) continue;
        
        $categoryLabel = $categories[$categorySlug]['label'] ?? $categorySlug;
        
        echo '<div class="category">
            <h2>' . htmlspecialchars($categoryLabel) . ' Reports (' . count($reports) . ')</h2>
            <table>
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Full Name</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Purpose/Description</th>
                        <th>Status</th>
                        <th>Image</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($reports as $report) {
            $timestamp = $report['timestamp'] ?? '';
            $createdDate = '';
            
            if ($timestamp) {
                if (is_string($timestamp)) {
                    $createdDate = date('M j, Y g:i A', strtotime($timestamp));
                } elseif (is_array($timestamp) && isset($timestamp['_seconds'])) {
                    $createdDate = date('M j, Y g:i A', $timestamp['_seconds']);
                }
            }
            
            // Get image URL if available
            $imageUrl = '';
            $imageDisplay = 'No Image';
            if (!empty($report['imageUrl'])) {
                $imageUrl = $report['imageUrl'];
                $imageDisplay = '<a href="' . htmlspecialchars($imageUrl) . '" target="_blank" style="color: #2563eb; text-decoration: underline;">View Image</a>';
            } elseif (!empty($report['image'])) {
                $imageUrl = $report['image'];
                $imageDisplay = '<a href="' . htmlspecialchars($imageUrl) . '" target="_blank" style="color: #2563eb; text-decoration: underline;">View Image</a>';
            }
            
            $statusClass = 'status-' . strtolower($report['status'] ?? 'pending');
            
            echo '<tr>
                <td>' . htmlspecialchars($report['_id'] ?? '') . '</td>
                <td>' . htmlspecialchars($report['fullName'] ?? '') . '</td>
                <td>' . htmlspecialchars($report['contact'] ?? '') . '</td>
                <td>' . htmlspecialchars($report['location'] ?? '') . '</td>
                <td>' . htmlspecialchars(substr($report['purpose'] ?? '', 0, 100)) . (strlen($report['purpose'] ?? '') > 100 ? '...' : '') . '</td>
                <td class="' . $statusClass . '">' . htmlspecialchars($report['status'] ?? '') . '</td>
                <td>' . $imageDisplay . '</td>
                <td>' . htmlspecialchars($createdDate) . '</td>
            </tr>';
        }
        
        echo '</tbody></table></div>';
    }
    
    echo '</body></html>';
    exit();
}

// Handle the export request
$format = $_GET['format'] ?? 'excel';
$category = $_GET['category'] ?? 'all';

// Validate format
if (!in_array($format, ['excel', 'pdf'])) {
    http_response_code(400);
    exit('Invalid format');
}

// Get reports based on category filter
$allReports = [];

if ($category === 'all') {
    // Get all categories
    foreach ($categories as $slug => $meta) {
        $reports = get_all_reports($meta['collection']);
        if (!empty($reports)) {
            $allReports[$slug] = $reports;
        }
    }
} else {
    // Get specific category
    if (isset($categories[$category])) {
        $reports = get_all_reports($categories[$category]['collection']);
        if (!empty($reports)) {
            $allReports[$category] = $reports;
        }
    }
}

// Export based on format
if ($format === 'excel') {
    export_to_excel($allReports, $categories);
} else {
    export_to_pdf($allReports, $categories);
}
