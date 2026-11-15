        <!-- TAB 3: KNOWLEDGE BASE MANAGEMENT -->
        <section id="ai-tab-knowledge" class="ai-panel <?php echo $activeTab === 'knowledge' ? 'active' : ''; ?>">
            <div class="admin-card" id="kbEditorCard" style="margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 id="kbFormHeading" style="font-size:15px; font-weight:700; margin:0;">Add New Multilingual FAQ Entry</h3>
                    <button type="button" id="kbCancelEditBtn" class="btn-secondary" style="display:none; padding:5px 12px; font-size:12px;">Cancel Edit</button>
                </div>
                <form method="POST" id="kbEditorForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_kb">
                    <input type="hidden" name="kb_id" id="kbFormId" value="0">
                    <div class="admin-form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div class="admin-form-group">
                            <label for="kbFormCategory">Category</label>
                            <select name="category" id="kbFormCategory">
                                <?php foreach ($allowedCategories as $catKey => $catLabel): ?>
                                    <option value="<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($catLabel); ?> (<?php echo htmlspecialchars($catKey); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-group">
                            <label for="kbFormLanguage">Language</label>
                            <select name="language" id="kbFormLanguage">
                                <?php foreach ($supportedLanguages as $sLang): ?>
                                    <option value="<?php echo htmlspecialchars($sLang); ?>"><?php echo htmlspecialchars($sLang); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label for="kbFormQuestion">Guest Question</label>
                        <input type="text" name="question" id="kbFormQuestion" required maxlength="300" placeholder="e.g. What time is check-in and check-out?">
                    </div>
                    <div class="admin-form-group" style="margin-bottom:12px;">
                        <label for="kbFormAnswer">Grounded Official Answer</label>
                        <textarea name="answer" id="kbFormAnswer" rows="3" required style="width:100%; padding:10px; border:1px solid var(--border, #CBD5E1); border-radius:8px;" placeholder="Enter the official resort answer..."></textarea>
                    </div>
                    <button type="submit" id="kbSubmitBtn" class="btn-primary">Save FAQ Entry</button>
                </form>
            </div>

            <div class="admin-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                    <h3 style="font-size:15px; font-weight:700; margin:0;">Existing Knowledge Base Entries (<?php echo count($kbRows); ?>)</h3>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <input type="text" id="kbSearchFilter" placeholder="Filter FAQs by keyword..." style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px; min-width:200px;">
                        <select id="kbCategoryFilter" style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px;">
                            <option value="all">All Categories</option>
                            <?php foreach ($allowedCategories as $catKey => $catLabel): ?>
                                <option value="<?php echo htmlspecialchars($catKey); ?>"><?php echo htmlspecialchars($catLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="kbLanguageFilter" style="padding:7px 10px; border:1px solid var(--border, #CBD5E1); border-radius:8px; font-size:12.5px;">
                            <option value="all">All Languages</option>
                            <?php foreach ($supportedLanguages as $sLang): ?>
                                <option value="<?php echo htmlspecialchars($sLang); ?>"><?php echo htmlspecialchars($sLang); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <table class="admin-table" id="kbEntriesTable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Language</th>
                            <th>Question &amp; Answer</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($kbRows as $kb): ?>
                        <tr class="kb-row"
                            data-kb-category="<?php echo htmlspecialchars((string)$kb['category']); ?>"
                            data-kb-language="<?php echo htmlspecialchars((string)$kb['language']); ?>"
                            data-kb-search="<?php echo htmlspecialchars(strtolower($kb['category'] . ' ' . $kb['language'] . ' ' . $kb['question'] . ' ' . $kb['answer'])); ?>">
                            <td>#<?php echo (int)$kb['id']; ?></td>
                            <td><span class="badge badge-confirmed"><?php echo htmlspecialchars((string)$kb['category']); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$kb['language']); ?></td>
                            <td>
                                <strong>Q: <?php echo htmlspecialchars((string)$kb['question']); ?></strong><br>
                                <span style="font-size:12px; color:var(--text-muted);">A: <?php echo htmlspecialchars((string)$kb['answer']); ?></span>
                            </td>
                            <td><?php echo (int)$kb['is_active'] === 1 ? '<span class="badge badge-checkedin">Active</span>' : '<span class="badge badge-checkedout">Disabled</span>'; ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-secondary js-edit-kb" style="padding:4px 8px; font-size:11px;"
                                    data-kb-entry="<?php echo htmlspecialchars(json_encode([
                                        'id' => (int)$kb['id'],
                                        'category' => (string)$kb['category'],
                                        'language' => (string)$kb['language'],
                                        'question' => (string)$kb['question'],
                                        'answer' => (string)$kb['answer'],
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>">
                                    Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_kb">
                                    <input type="hidden" name="kb_id" value="<?php echo (int)$kb['id']; ?>">
                                    <button type="submit" class="btn-secondary" style="padding:4px 8px; font-size:11px;"><?php echo (int)$kb['is_active'] === 1 ? 'Disable' : 'Enable'; ?></button>
                                </form>
                                <form method="POST" class="js-delete-kb-form" data-kb-id="<?php echo (int)$kb['id']; ?>" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_kb">
                                    <input type="hidden" name="kb_id" value="<?php echo (int)$kb['id']; ?>">
                                    <button type="submit" class="btn-danger" style="padding:4px 8px; font-size:11px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
