<?php

namespace APP\plugins\generic\acceptanceLetter\classes\service;

use APP\plugins\generic\acceptanceLetter\classes\model\IssuedCertificate;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use APP\core\Application;

class VerificationService
{
    /**
     * Generate a unique verification token
     */
    public static function generateToken(int $contextId, int $submissionId): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a certificate number (e.g., ACC-YYYY-CONTEXTID-SUBMISSIONID-RANDOM)
     */
    public static function generateCertificateNumber(int $contextId, int $submissionId): string
    {
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        return sprintf('ACC-%s-%d-%d-%s', $year, $contextId, $submissionId, $random);
    }

    /**
     * Generate QR Code as Base64 data URI
     */
    public static function generateQrCodeDataUri(string $url): string
    {
        if (class_exists(QRCode::class)) {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_L,
                'scale'      => 4,
            ]);
            $qrcode = new QRCode($options);
            return $qrcode->render($url);
        }

        // Fallback placeholder if QR library is not yet installed
        return '';
    }

    /**
     * Build public verification URL for a token
     */
    public static function getVerificationUrl(string $token, string $journalPath): string
    {
        $request = Application::get()->getRequest();
        return $request->url($journalPath, 'acceptance', 'verify', [$token]);
    }
}
