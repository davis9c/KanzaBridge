<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-lock"></i> Hashids Test
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Support Status -->
                    <?php if ($check['supported']): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Hashids Support:</strong> ✓ Supported
                            <br>
                            <strong>Using Extension:</strong> <code><?= $check['extension'] ?></code>
                            <br>
                            <strong>PHP SAPI:</strong> <code><?= $check['php_sapi'] ?></code>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i>
                            <strong>Error:</strong> <?= $check['message'] ?>
                        </div>
                    <?php endif; ?>

                    <!-- Test Results -->
                    <?php if (isset($test)): ?>
                        <h5 class="mt-4 mb-3">Encoding/Decoding Test</h5>

                        <?php if ($test['success']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Test Passed!
                            </div>

                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Original ID:</strong></td>
                                    <td><code><?= $test['original'] ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong>Encoded Hash:</strong></td>
                                    <td><code><?= $test['encoded'] ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong>Decoded Result:</strong></td>
                                    <td><code><?= $test['decoded'] ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong>Match:</strong></td>
                                    <td>
                                        <?php if ($test['match']): ?>
                                            <span class="badge bg-success">✓ Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">✗ No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <div class="alert alert-info mt-3">
                                <strong>Usage in Views:</strong>
                                <pre><code>&lt;a href="&lt;?= site_url('user/' . hashid_encode($user['id'])) ?>"&gt;
    Edit
&lt;/a&gt;</code></pre>
                            </div>

                            <div class="alert alert-info">
                                <strong>Usage in Controller:</strong>
                                <pre><code>$id = hashid_decode($hash);
$user = $this->userModel->find($id);</code></pre>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i>
                                <strong>Test Failed:</strong><br>
                                <?= $test['error'] ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Additional Tests -->
                    <h5 class="mt-4 mb-3">Test Cases</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Unit Tests</h6>
                                    <p class="small text-muted mb-2">Run automated tests</p>
                                    <form action="<?= base_url('/') ?>" method="get" style="display:inline;">
                                        <button type="button" class="btn btn-sm btn-info" onclick="runTests()">
                                            <i class="fas fa-flask"></i> Run Tests
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>API Check</h6>
                                    <p class="small text-muted mb-2">Get JSON response</p>
                                    <a href="/diagnose/check-json" class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-code"></i> API Response
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-exclamation-triangle"></i> If Support is Not Available
                    </h6>
                </div>
                <div class="card-body">
                    <p><strong>Option 1: Use Setup Script (Recommended)</strong></p>
                    <pre><code>cd /path/to/project
bash setup-hashids.sh bcmath</code></pre>

                    <p class="mt-3"><strong>Option 2: Manual Installation</strong></p>
                    <pre><code>sudo apt-get update
sudo apt-get install -y php-bcmath
sudo phpenmod bcmath
sudo systemctl restart apache2</code></pre>

                    <p class="mt-3"><strong>Option 3: Using Docker</strong></p>
                    <pre><code>RUN docker-php-ext-install bcmath</code></pre>

                    <p class="mt-3">
                        <a href="/diagnose/extensions" class="btn btn-sm btn-warning">
                            <i class="fas fa-arrow-left"></i> Back to Extensions
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-lightbulb"></i> Quick Info
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <strong>Test ID:</strong> <code>123</code>
                    </p>
                    <p class="small mb-3 text-muted">
                        This test encodes ID 123 to a hash and then decodes it back.
                    </p>

                    <hr>

                    <p class="small"><strong>Features:</strong></p>
                    <ul class="small text-muted mb-0">
                        <li>Generate short, unique IDs</li>
                        <li>Obfuscate database IDs</li>
                        <li>Non-sequential hashes</li>
                        <li>URL-safe encoding</li>
                    </ul>

                    <hr>

                    <p class="small"><strong>Implementation:</strong></p>
                    <ul class="small text-muted mb-0">
                        <li>Helper: <code>app/Helpers/hashid_helper.php</code></li>
                        <li>Config: <code>.env</code> (HASHIDS_SALT)</li>
                        <li>Tests: <code>tests/unit/HashidTest.php</code></li>
                    </ul>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <p class="small"><strong>Current Settings:</strong></p>
                    <table class="table table-sm table-borderless small">
                        <tr>
                            <td>Extension:</td>
                            <td><code><?= $check['extension'] ?? 'N/A' ?></code></td>
                        </tr>
                        <tr>
                            <td>SAPI:</td>
                            <td><code><?= $check['php_sapi'] ?></code></td>
                        </tr>
                        <tr>
                            <td>Status:</td>
                            <td>
                                <?php if ($check['supported']): ?>
                                    <span class="badge bg-success">OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ERROR</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function runTests() {
        alert('Tests dapat dijalankan dengan perintah:\nphp spark test');
    }
</script>

<?= $this->endSection() ?>