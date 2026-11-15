        <!-- TAB 5: SETTINGS & PRIVACY -->
        <section id="ai-tab-settings" class="ai-panel <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
            <div class="admin-card" style="margin-bottom:20px;">
                <h3 style="font-size:15px; font-weight:700; margin-bottom:12px;">Assistant Configuration</h3>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_settings">
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>
                            <input type="checkbox" name="chatbot_enabled" value="1" <?php echo $chatbotEnabled === '1' ? 'checked' : ''; ?>>
                            Enable Guest AI Concierge Widget on Public Pages
                        </label>
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>Daily Global Message Cap</label>
                        <input type="number" name="chatbot_daily_limit" min="50" max="10000" value="<?php echo htmlspecialchars($chatbotDailyLimit); ?>">
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label>Welcome Message</label>
                        <textarea name="chatbot_welcome_message" rows="2" style="width:100%; padding:10px; border:1px solid var(--border, #CBD5E1); border-radius:8px;"><?php echo htmlspecialchars($chatbotWelcome); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Save Settings</button>
                </form>
            </div>

            <div class="admin-card">
                <h3 style="font-size:15px; font-weight:700; margin-bottom:8px;">Data Privacy Act (RA 10173) &mdash; 90-Day Log Retention</h3>
                <p style="font-size:13px; color:var(--text-muted); margin-bottom:12px;">
                    All guest emails and mobile numbers in chat messages are automatically masked prior to storage. Conversations older than 90 days can be purged below or via <code>php backend/cron/purge_chatbot_logs.php</code>.
                </p>
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="purge_old_logs">
                    <button type="submit" class="btn-danger">Purge Logs Older Than 90 Days</button>
                </form>
            </div>
        </section>
