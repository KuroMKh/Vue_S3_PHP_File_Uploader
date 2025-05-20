<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bucketName = $_ENV["S3_BUCKET_NAME"];

$s3 = new S3Client([
    'version' => $_ENV["S3_VERSION"],
    'region' => $_ENV["S3_REGION"],
    'credentials' => [
        'key' => $_ENV["S3_KEY"],
        'secret' => $_ENV["S3_SECRET"],
    ],
]);

$objects = $s3->listObjects([
    'Bucket' => $bucketName
]);

$slimObjects = array_map(function ($item) {
    return [
        'Key' => $item['Key']
    ];
}, $objects['Contents']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>File Upload Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <!-- Upload App -->
    <div id="uploadApp" class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-4">Upload a File</h4>

                <form method="POST" enctype="multipart/form-data" action="upload.php" @submit.prevent="handleSubmit">
                    <div class="mb-3">
                        <label for="fileInput" class="form-label">Select your file</label>
                        <input type="file" name="file" id="fileInput" class="form-control" @change="onFileChange">
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">Selected file: {{ file ? file.name : 'none' }}</small>
                    </div>

                    <div v-if="errorMessage" class="alert alert-danger position-relative py-2">
                        {{ errorMessage }}
                        <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2"
                            @click="errorMessage = ''" aria-label="Close"></button>
                    </div>

                    <div v-if="statusMessage" class="alert alert-success position-relative py-2">
                        {{ statusMessage }}
                        <button type="button" class="btn-close position-absolute top-0 end-0 mt-2 me-2"
                            @click="statusMessage = ''" aria-label="Close"></button>
                    </div>

                    <button type="submit" class="btn btn-primary"><i
                            class="fas fa-cloud-download me-2"></i>Upload</button>
                </form>
            </div>
        </div>

        <!-- File List Table -->
        <div class="card shadow-sm mt-4">
            <div class="card-body">
                <h4 class="card-title mb-4">A Peek at My Files</h4>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr class="text-center">
                                <th>No.</th>
                                <th>File Name</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in items" :key="index">
                                <td style="text-align: center; vertical-align: middle;">{{ index + 1 }}</td>
                                <td style="text-align: center; vertical-align: middle;">{{ item.Key }}</td>
                                <td style="text-align: center; vertical-align: middle;"> <button type="button"
                                        class="btn btn-primary me-2"><i
                                            class="fas fa-cloud-download me-2"></i>Download</button>
                                    <button type="button" class="btn btn-danger"><i
                                            class="fas fa-trash me-2"></i>Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Vue Script -->
    <script>
        let serveData = <?= json_encode($slimObjects) ?>;
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    file: null,
                    errorMessage: "",
                    statusMessage: "",
                    items: serveData
                };
            },
            methods: {
                onFileChange(event) {
                    this.file = event.target.files[0];
                },
                handleSubmit(event) {
                    if (!this.file) {
                        this.errorMessage = "Please select a file before uploading.";
                        return;
                    }

                    this.errorMessage = "";

                    const formData = new FormData();
                    formData.append('file', this.file);

                    fetch('upload.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.text())
                        .then(data => {
                            if (data.includes("successfully")) {
                                this.statusMessage = data;
                            } else {
                                this.errorMessage = data;
                            }
                        })
                        .catch(error => {
                            this.errorMessage = "Error uploading file: " + error.message;
                        });

                    fetch('delete.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.text())
                        .then(data => {
                            if (data.includes("successfully")) {
                                this.statusMessage = data;
                            } else {
                                this.errorMessage = data;
                            }
                        })
                        .catch(error => {
                            this.errorMessage = "Error uploading file: " + error.message;
                        });
                }
            }
        }).mount("#uploadApp");
    </script>

</body>

</html>