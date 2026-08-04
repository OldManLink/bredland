<!-- Start of telemetry-drawer snapshot -->
                <template id="<?= htmlspecialchars($client['host'], ENT_QUOTES, 'UTF-8') ?>-telemetry-template">
                    <pre class="telemetry"><?= htmlspecialchars(latest_jsonl_line($client['heartbeat_file']), ENT_QUOTES, 'UTF-8') ?></pre>
                </template>
                <!-- End of telemetry-drawer snapshot -->