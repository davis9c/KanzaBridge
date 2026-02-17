<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plug"></i> Extensions Diagnostics
                    </h6>
                </div>
                <div class="card-body">
                    <!-- PHP Version & SAPI -->
                    <div class="alert alert-info">
                        <strong>PHP Version:</strong> <?= $info['php_version'] ?><br>
                        <strong>SAPI:</strong> <?= $info['php_sapi'] ?>
                    </div>

                    <!-- Hashids Support -->
                    <h5 class="mb-3">Hashids Support</h5>
                    <?php if ($hashidCheck['supported']): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Status:</strong> Supported
                            <br>
                            <strong>Active Extension:</strong> <code><?= $hashidCheck['extension'] ?></code>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i>
                            <strong>Status:</strong> Not Supported
                            <br>
                            <strong>Issue:</strong> <?= $hashidCheck['message'] ?>
                            <br><br>
                            <strong>Solution:</strong>
                            <pre><code>sudo apt-get install -y php-bcmath
sudo phpenmod bcmath
sudo systemctl restart apache2</code></pre>
                        </div>
                    <?php endif; ?>

                    <!-- All Extensions -->
                    <h5 class="mt-4 mb-3">Math Extensions Status</h5>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Extension</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($info['extensions'] as $name => $loaded): ?>
                                <tr>
                                    <td><code><?= $name ?></code></td>
                                    <td>
                                        <?php if ($loaded): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Loaded
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times"></i> Not Loaded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- All Loaded Extensions (Collapsed) -->
                    <h5 class="mt-4 mb-3">All Loaded Extensions</h5>
                    <div class="alert alert-light border">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                            data-bs-target="#extensionsList">
                            Show All Extensions (<?= count($info['loaded_extensions']) ?>)
                        </button>
                        <div class="collapse mt-3" id="extensionsList">
                            <div style="max-height: 300px; overflow-y: auto; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <?php foreach ($info['loaded_extensions'] as $ext): ?>
                                    <span class="badge bg-primary me-1 mb-1"><?= $ext ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Troubleshooting Card -->
            <div class="card shadow">
                <div class="card-header py-3 d-flex align-items-center">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-wrench"></i> Troubleshooting
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!$hashidCheck['supported']): ?>
                        <h6>Quick Fix:</h6>
                        <ol>
                            <li>Run installation script:
                                <pre><code>bash setup-hashids.sh bcmath</code></pre>
                            </li>
                            <li>Or manually install:
                                <pre><code>sudo apt-get install -y php-bcmath
sudo phpenmod bcmath
sudo systemctl restart apache2</code></pre>
                            </li>
                            <li>Verify installation:
                                <pre><code>php -m | grep bcmath</code></pre>
                            </li>
                        </ol>
                        <p><a href="/diagnose/hashid" class="btn btn-sm btn-primary">Test Hashids</a></p>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Your system is properly configured for Hashids!
                            <br>
                            <a href="/diagnose/hashid" class="btn btn-sm btn-primary mt-2">Run Hashids Test</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <div class="card shadow mb-4 bg-light">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-info-circle"></i> Information
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small"><strong>What is Hashids?</strong></p>
                    <p class="small text-muted">
                        Hashids is a library to generate short unique ids from numbers. It's commonly used to obfuscate
                        database IDs in URLs.
                    </p>

                    <hr>

                    <p class="small"><strong>Required Extensions:</strong></p>
                    <ul class="small text-muted mb-0">
                        <li><code>bcmath</code> (BC Math - Binary Calculator)</li>
                        <li><code>gmp</code> (GNU Multiple Precision)</li>
                    </ul>

                    <hr>

                    <p class="small"><strong>Links:</strong></p>
                    <ul class="small">
                        <li><a href="https://hashids.org/php" target="_blank">Hashids Documentation</a></li>
                        <li><a href="<?= base_url('docs/HASHID_TROUBLESHOOTING.md') ?>" target="_blank">Troubleshooting Guide</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>