<?php
session_start();

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Get parameters
$type = $_GET['type'] ?? 'csv';
$period = $_GET['period'] ?? '6m';
$model = $_GET['model'] ?? 'prophet';
$product_id = $_GET['product_id'] ?? 'all';

// Get forecast data from session
$forecast_data = $_SESSION['forecast_data'] ?? [];

// Simple export functions
switch($type) {
    case 'pdf':
        exportPDFFast($forecast_data);
        break;
        
    case 'excel':
        exportExcelFast($forecast_data);
        break;
        
    case 'csv':
        exportCSVFast($forecast_data);
        break;
        
    default:
        exportCSVFast($forecast_data);
}

function exportPDFFast($data) {
    // Create simple HTML that can be printed as PDF
    $html = '<h2>Sales Forecast</h2><table border=1><tr><th>Month</th><th>Predicted</th><th>Actual</th></tr>';
    
    if (!empty($data['dates'])) {
        for ($i = 0; $i < count($data['dates']); $i++) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($data['dates'][$i]) . '</td>';
            $html .= '<td>Rs. ' . number_format($data['predicted'][$i] ?? 0, 0) . '</td>';
            $html .= '<td>' . (isset($data['actual'][$i]) ? 'Rs. ' . number_format($data['actual'][$i], 0) : '-') . '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</table>';
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="forecast_' . date('Y-m-d') . '.pdf"');
    
    // Output simple HTML that can be saved as PDF from browser
    echo $html;
    exit();
}

function exportExcelFast($data) {
    // Simple Excel format
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="forecast_' . date('Y-m-d') . '.xls"');
    
    echo "Month\tPredicted (Rs)\tActual (Rs)\tLower Bound\tUpper Bound\n";
    
    if (!empty($data['dates'])) {
        for ($i = 0; $i < count($data['dates']); $i++) {
            echo $data['dates'][$i] . "\t";
            echo ($data['predicted'][$i] ?? 0) . "\t";
            echo ($data['actual'][$i] ?? '') . "\t";
            echo ($data['lower'][$i] ?? 0) . "\t";
            echo ($data['upper'][$i] ?? 0) . "\n";
        }
    }
    
    exit();
}

function exportCSVFast($data) {
    // Fast CSV export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="forecast_' . date('Y-m-d') . '.csv"');
    
    // Output headers
    echo "Month,Predicted (Rs),Actual (Rs),Lower Bound,Upper Bound\n";
    
    // Output data
    if (!empty($data['dates'])) {
        for ($i = 0; $i < count($data['dates']); $i++) {
            echo '"' . $data['dates'][$i] . '",';
            echo ($data['predicted'][$i] ?? 0) . ',';
            echo ($data['actual'][$i] ?? '') . ',';
            echo ($data['lower'][$i] ?? 0) . ',';
            echo ($data['upper'][$i] ?? 0) . "\n";
        }
    }
    
    exit();
}