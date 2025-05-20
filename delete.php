<?php
// Step 1: Include AWS SDK (the tool that lets PHP talk to AWS services)
require 'vendor/autoload.php'; // This loads the SDK so we can use it

//Use .env 
use Dotenv\Dotenv;
// Use AWS S3 client to talk to S3 service
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


// Step 2: Set up your AWS connection information
$s3 = new S3Client([
    'version' => $_ENV["S3_VERSION"],  // The ?? operator provides a default if the key doesn't exist
    'region' => $_ENV["S3_REGION"],
    'credentials' => [
        'key' => $_ENV["S3_KEY"],
        'secret' => $_ENV["S3_SECRET"],
    ],
]);

// Your S3 bucket name
$bucketName = $_ENV["S3_BUCKET_NAME"]; // Your bucket name

// Step 3: Check if a file is being uploaded through the form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']; // Get the file from the form

    // Check if there was an upload error
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

    // Step 4: Try uploading the file to S3
    try {
        $result = $s3->deleteObject([
            'Bucket' => $bucketName, // The name of your S3 bucket
            'Key' => basename($file['name']), // The name of the file (it gets saved with the same name)
            'SourceFile' => $file['tmp_name'], // Temporary file path of the uploaded file
            // No ACL parameter needed as the bucket is private
        ]);

        // Step 5: If successful, show the presigned URL of the uploaded file
        echo "✅ File Delete successfully";
    } catch (AwsException $e) {
        // Step 6: If something goes wrong, show the error message
        echo "❌ Error: " . $e->getMessage(); // Show the error that occurred
    }
} else {
    // No file uploaded or not a POST request
    echo "❌ No file was uploaded or incorrect request method";
}

