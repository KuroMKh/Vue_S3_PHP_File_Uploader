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

    <style>
        body {
            background-color: #f8f9fc;
        }

        .upload-zone {
            border: 2px dashed #6c757d;
            border-radius: 8px;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #ffffff;
        }

        .upload-zone:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }

        .upload-zone.dragover {
            border-color: #198754;
            background-color: #f0fff4;
        }

        .file-input {
            display: none;
        }

        .btn-rounded {
            border-radius: 20px;
            padding: 8px 20px;
        }

        .card-clean {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }

        .table-clean {
            border-radius: 8px;
            overflow: hidden;
        }

        .table-clean thead {
            background-color: #495057;
            color: white;
        }

        .table-clean tbody tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div id="uploadApp">

            <!-- Header -->
            <div class="text-center mb-4">
                <h2 class="text-dark">File Manager</h2>
                <p class="text-muted">Upload and manage your files</p>
            </div>

            <!-- Upload Section -->
            <div class="card card-clean mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-upload me-2"></i>Upload File
                    </h5>

                    <form method="POST" enctype="multipart/form-data" action="upload.php"
                        @submit.prevent="handleSubmit">

                        <!-- Upload Zone -->
                        <div class="upload-zone" @click="$refs.fileInput.click()" @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false" @drop.prevent="handleDrop"
                            :class="{ 'dragover': dragOver }">

                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <h6 class="mb-2">Drop files here or click to browse</h6>
                            <small class="text-muted">All file types supported</small>

                            <input type="file" name="file" ref="fileInput" class="file-input" @change="onFileChange">
                        </div>

                        <!-- Selected File Info -->
                        <div v-if="file" class="alert alert-info mt-3">
                            <i class="fas fa-file me-2"></i>
                            <strong>Selected:</strong> {{ file.name }} ({{ formatFileSize(file.size) }})
                        </div>

                        <!-- Messages -->
                        <div v-if="errorMessage" class="alert alert-danger mt-3">
                            {{ errorMessage }}
                            <button type="button" class="btn-close float-end" @click="errorMessage = ''"></button>
                        </div>

                        <div v-if="statusMessage" class="alert alert-success mt-3">
                            {{ statusMessage }}
                            <button type="button" class="btn-close float-end" @click="statusMessage = ''"></button>
                        </div>

                        <!-- Upload Button -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-rounded" :disabled="!file">
                                <i class="fas fa-upload me-2"></i>
                                Upload File
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- File List Section -->
            <div class="card card-clean">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-folder me-2"></i>
                        My Files
                        <span class="badge bg-secondary ms-2">{{ items.length }}</span>
                    </h5>

                    <!-- Empty State -->
                    <div v-if="items.length === 0" class="text-center py-4">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                        <h6 class="text-muted">No files uploaded yet</h6>
                    </div>

                    <!-- Files Table -->
                    <div v-else class="table-responsive">
                        <table class="table table-clean table-hover">
                            <thead>
                                <tr>
                                    <th width="80">#</th>
                                    <th>File Name</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in items" :key="index">
                                    <td class="text-center">{{ index + 1 }}</td>
                                    <td>
                                        <i :class="getFileIcon(item.Key)" class="me-2"></i>
                                        {{ item.Key }}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-2"
                                            @click="downloadFile(item.Key)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" @click="deleteFile(item.Key)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                    items: serveData,
                    dragOver: false
                };
            },
            methods: {
                onFileChange(event) {
                    this.file = event.target.files[0];
                },
                handleDrop(event) {
                    this.dragOver = false;
                    const files = event.dataTransfer.files;
                    if (files.length > 0) {
                        this.file = files[0];
                        this.$refs.fileInput.files = files;
                    }
                },
                formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                },
                getFileIcon(filename) {
                    const ext = filename.split('.').pop().toLowerCase();
                    const icons = {
                        'pdf': 'fas fa-file-pdf text-danger',
                        'doc': 'fas fa-file-word text-primary',
                        'docx': 'fas fa-file-word text-primary',
                        'xls': 'fas fa-file-excel text-success',
                        'xlsx': 'fas fa-file-excel text-success',
                        'jpg': 'fas fa-file-image text-info',
                        'jpeg': 'fas fa-file-image text-info',
                        'png': 'fas fa-file-image text-info',
                        'mp4': 'fas fa-file-video text-warning',
                        'mp3': 'fas fa-file-audio text-warning',
                        'zip': 'fas fa-file-archive text-secondary',
                        'txt': 'fas fa-file-alt text-muted'
                    };
                    return icons[ext] || 'fas fa-file text-muted';
                },
                downloadFile(filename) {
                    console.log('Download:', filename);
                },
                deleteFile(filename) {
                    if (confirm(`Delete "${filename}"?`)) {
                        console.log('Delete:', filename);
                    }
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