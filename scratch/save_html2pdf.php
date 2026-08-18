<?php
$source = 'C:\Users\User\.gemini\antigravity-ide\brain\3a45feaa-7a60-4fd7-b476-4d4411b7958b\.system_generated\steps\250\content.md';
$target = 'c:\xampp\htdocs\Kalamedia\assets\js\html2pdf.bundle.min.js';

if (file_exists($source)) {
    copy($source, $target);
    echo "Copied html2pdf bundle locally! Size: " . filesize($target) . " bytes\n";
} else {
    echo "Source not found\n";
}
