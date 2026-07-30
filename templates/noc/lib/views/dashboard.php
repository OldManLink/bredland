<div id="refresh-indicator">
    <div class="spinner"></div>
</div>
<div class="dashboard">
    <div class="cards-row">
        <?php foreach ($clients as $client): ?>
        <div class="card-slot">
            <div class="card-container">
                <div class="card <?= heartbeat_health_colour($client['age']) ?>">
                    <h1>
                        <span class="led <?= heartbeat_health_colour($client['age']) ?>"></span>
                        <?= htmlspecialchars($client['title'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <p>Last heartbeat: <?= htmlspecialchars(format_heartbeat_age($client['age']), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php foreach ($client['fields'] as $field): ?>
                    <p><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars(display_client_field($client, $field), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                    <button class="drawer-handle" type="button" data-telemetry-toggle="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>">=</button>
                </div>
            </div>
            <template id="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>-telemetry-template">
                <pre class="telemetry"><?= htmlspecialchars(latest_jsonl_line($client['heartbeat_file']), ENT_QUOTES, 'UTF-8') ?></pre>
            </template>
        </div>
        <?php endforeach; ?>
    </div>
</div>
