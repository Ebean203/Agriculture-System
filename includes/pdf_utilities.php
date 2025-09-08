<?php
// Standardized PDF utilities for Agriculture System
// Using system colors: #16a34a (agri-green), #15803d (agri-dark)

class StandardPDF {
    private $content = '';
    private $title = '';
    private $subtitle = '';
    
    public function __construct($title = 'Document', $subtitle = '') {
        $this->title = $title;
        $this->subtitle = $subtitle;
    }
    
    public function addContent($html) {
        $this->content .= $html;
    }
    
    public function addHeader($record_count = 0, $additional_stats = []) {
        $stats_html = '';
        
        // Default stats
        $stats = [
            ['number' => $record_count, 'label' => 'Total Records'],
            ['number' => date('Y'), 'label' => 'Export Year'],
            ['number' => date('M d'), 'label' => 'Export Date']
        ];
        
        // Merge additional stats
        if (!empty($additional_stats)) {
            $stats = array_merge($additional_stats, array_slice($stats, 1));
        }
        
        foreach ($stats as $stat) {
            $stats_html .= '<div class="stat-item">
                <div class="stat-number">' . $stat['number'] . '</div>
                <div class="stat-label">' . $stat['label'] . '</div>
            </div>';
        }
        
        $header = '<div class="header">
            <div class="title">🌾 LAGONGLONG FARMS</div>
            <div class="subtitle">' . $this->title . '</div>';
            
        if ($this->subtitle) {
            $header .= '<div class="subtitle">' . $this->subtitle . '</div>';
        }
        
        $header .= '<div class="export-info">Generated on: ' . date('F d, Y g:i A') . ' | Total Records: ' . $record_count . '</div>
        </div>
        
        <div class="summary-stats">' . $stats_html . '</div>';
        
        $this->content = $header . $this->content;
    }
    
    public function addFooter($record_count = 0, $document_type = 'Export') {
        $footer = '<div class="footer">
            <div style="font-weight: bold; color: #16a34a;">🌾 LAGONGLONG FARMS - Agriculture System</div>
            <div style="margin-top: 8px;">This ' . strtolower($document_type) . ' contains ' . $record_count . ' records exported on ' . date('F d, Y') . '</div>
            <div style="margin-top: 5px; font-style: italic;">Confidential - For Official Use Only</div>
        </div>';
        
        $this->content .= $footer;
    }
    
    public function output($filename = 'document') {
        // Standard CSS for all PDF exports
        $css = '<style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
            color: #2c3e50;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #16a34a;
            padding-bottom: 15px;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            margin: -20px -20px 30px -20px;
            padding: 20px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .export-info {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #15803d;
            font-size: 11px;
        }
        td {
            padding: 10px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        tr:hover {
            background-color: #dcfce7;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-yes {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .badge-no {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .badge-active {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-inactive {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            border-top: 2px solid #16a34a;
            padding-top: 15px;
        }
        .summary-stats {
            background: #f0fdf4;
            border: 1px solid #16a34a;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #16a34a;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        @page {
            size: A4 landscape;
            margin: 0.5in;
        }
    </style>';
        
        // Create complete HTML document
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $this->title . '</title>
    ' . $css . '
</head>
<body>' . $this->content . '</body>
</html>';
        
        // Set headers for download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        echo $html;
    }
}

// Utility function to format data consistently
function formatPdfData($value, $type = 'text') {
    if (is_null($value) || $value === '') return '-';
    
    switch ($type) {
        case 'date':
            return ($value && $value !== '0000-00-00') ? date('M d, Y', strtotime($value)) : '-';
        case 'datetime':
            return ($value && $value !== '0000-00-00 00:00:00') ? date('M d, Y g:i A', strtotime($value)) : '-';
        case 'number':
            return is_numeric($value) ? number_format((float)$value, 2) : $value;
        case 'hectares':
            return is_numeric($value) ? number_format((float)$value, 2) . ' ha' : $value;
        case 'yesno':
            return $value ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>';
        case 'badge':
            return $value === 'Yes' ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>';
        case 'status':
            return $value === 'Active' ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>';
        case 'currency':
            return is_numeric($value) ? '₱' . number_format((float)$value, 2) : $value;
        default:
            return htmlspecialchars(trim($value));
    }
}
?>
