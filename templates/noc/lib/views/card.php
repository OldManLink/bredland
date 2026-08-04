<!-- Start of card snapshot -->
                    <div class="card <?= heartbeat_health_colour($client['age']) ?>">
                        <h1>
                            <span class="led <?= heartbeat_health_colour($client['age']) ?>"></span>
                            <?= htmlspecialchars($client['title'] . "\n", ENT_QUOTES, 'UTF-8') ?>
                        </h1>
                        <p>Last heartbeat: <?= htmlspecialchars(format_heartbeat_age($client['age']), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php foreach ($client['fields'] as $field): ?>
<p><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(display_client_field($client, $field), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endforeach; ?><button class="drawer-handle" type="button" data-telemetry-toggle="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>">=</button>
                    </div>
                    <!-- End of card snapshot -->