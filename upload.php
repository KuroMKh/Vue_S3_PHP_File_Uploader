<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$s3 = new S3Client([
    'version' => $_ENV["S3_VERSION"],
    'region' => $_ENV["S3_REGION"],
    'credentials' => [
        'key' => $_ENV["S3_KEY"],
        'secret' => $_ENV["S3_SECRET"],
    ],
]);

$bucketName = $_ENV["S3_BUCKET_NAME"];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
            UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        ];

        echo "❌ Upload error: " . ($errorMessages[$file['error']] ?? "Unknown error");
        exit;
    }

    try {
        $result = $s3->putObject([
            'Bucket' => $bucketName,
            'Key' => basename($file['name']),
            'SourceFile' => $file['tmp_name'],
        ]);

        $presignedUrl = $s3->createPresignedRequest(
            $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key' => basename($file['name'])
            ]),
            '+1 hour'
        )->getUri()->__toString();

        echo "✅ File uploaded successfully. Access it here: " . $presignedUrl;

    } catch (AwsException $e) {
        echo "❌ Error: " . $e->getMessage();
    }
} else {
    echo "❌ No file was uploaded or incorrect request method";
}