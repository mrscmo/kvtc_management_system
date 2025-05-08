<?php
/**
 * pdf_generator.php - A utility file to generate PDFs for the Vocational Training Center
 * This should be placed in the /includes/ directory
 */

/**
 * Generate PDF Report
 * 
 * @param string $title The title of the PDF document
 * @param string $content The HTML content to be converted to PDF
 * @param string $filename The name for the downloaded file (without .pdf extension)
 * @param string $orientation The orientation of the page ('P' for Portrait, 'L' for Landscape)
 * @return void
 */
function generatePDF($title, $content, $filename, $orientation = 'P') {
    // Check if TCPDF is installed, if not, fallback to simple HTML download
    if (!file_exists(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php')) {
        // Display error message
        echo "<div class='alert alert-warning'>TCPDF library is not installed. Please install it using Composer.</div>";
        echo "<div class='alert alert-info'>Falling back to HTML view.</div>";
        echo "<h1>$title</h1>";
        echo $content;
        exit;
    }

    // Include TCPDF library
    require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

    // Create new PDF document
    $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Vocational Training Center');
    $pdf->SetAuthor('Vocational Training Center');
    $pdf->SetTitle($title);
    $pdf->SetSubject($title);

    // Remove header and footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');

    // Set margins
    $pdf->SetMargins(15, 15, 15);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(true, 15);

    // Set image scale factor
    $pdf->setImageScale(1.25);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();
    
    // Add logo
    $logo = __DIR__ . '/../assets/images/logo.png';
    if (file_exists($logo)) {
        $pdf->Image($logo, 15, 15, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }
    
    // Add title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, $title, 0, 1, 'C', 0, '', 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Add date
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'R', 0, '', 0);
    $pdf->Ln(5);
    
    // Write HTML content
    $pdf->writeHTML($content, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

/**
 * Generate financial table for PDF
 * 
 * @param array $data The financial data array
 * @param array $headers The table headers
 * @return string HTML table content
 */
function generateFinancialTable($data, $headers) {
    $html = '<table border="1" cellpadding="5">';
    
    // Add headers
    $html .= '<tr style="background-color: #f2f2f2; font-weight: bold;">';
    foreach ($headers as $header) {
        $html .= '<th>' . $header . '</th>';
    }
    $html .= '</tr>';
    
    // Add data rows
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    
    // Add closing tag
    $html .= '</table>';
    
    return $html;
}

/**
 * Format currency for PDF display
 * 
 * @param float $amount The amount to format
 * @return string Formatted currency string
 */
function formatPDFCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}