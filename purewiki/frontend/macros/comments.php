<?php
/**
 * PureWiki - Frontend Macro: Comments
 *
 * Renders comments and the comment form
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

// Check if comments are enabled in wiki settings and this page
if (empty($config['comments_enabled']) || empty($pageData['Settings']['enable_comments'])) {
    return;
}

require_once __DIR__ . '/../../core/comments.php';

$comments = getComments($contextPath);
$approvedComments = array_filter($comments, function($c) {
    return ($c['status'] ?? '') === 'approved';
});

$langCode = defined('CURRENT_LANG') ? CURRENT_LANG : ($config['i18n_default_lang'] ?? 'de');

?>
<section class="pw-comments-section" id="comments">
    <h3><?php echo __('comments.title') ?? 'Comments'; ?> (<?php echo count($approvedComments); ?>)</h3>

    <!-- Comments List -->
    <div class="pw-comments-list">
        <?php if (empty($approvedComments)): ?>
            <p><?php echo __('comments.no_comments') ?? 'No comments yet. Be the first!'; ?></p>
        <?php else: ?>
            <?php foreach ($approvedComments as $comment): 
                $dateStr = isset($comment['date']) ? date('d.m.Y H:i', strtotime($comment['date'])) : '';
            ?>
                <article class="pw-comment-item" id="<?php echo htmlspecialchars($comment['id']); ?>" style="margin-bottom: 1.5rem;">
                    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0;">
                        <strong class="pw-comment-author"><?php echo htmlspecialchars($comment['name']); ?></strong>
                        <small><?php echo htmlspecialchars($dateStr); ?></small>
                    </header>
                    <div style="white-space: pre-wrap; margin-top: 1rem;"><?php echo htmlspecialchars($comment['text']); ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="pw-comment-message" class="pw-comment-message" style="display: none; padding: 10px; margin-bottom: 15px; border-radius: 4px;"></div>

    <form class="pw-comment-form" id="pw-comment-form" method="POST" action=""
          data-api-endpoint="<?php echo BASE_PATH; ?>/purewiki/api.php?action=submit_comment"
          data-require-approval="<?php echo !empty($config['comments_require_approval']) ? 'true' : 'false'; ?>"
          data-pending-message="<?php echo htmlspecialchars(__('comments.pending_message') ?? 'Your comment has been submitted and is awaiting approval.'); ?>"
          data-success-message="<?php echo htmlspecialchars(__('comments.success_message') ?? 'Your comment has been posted successfully.'); ?>">
        <input type="hidden" name="path" value="<?php echo htmlspecialchars($contextPath); ?>">
        
        <fieldset class="grid">
            <label for="pw-comment-name">
                <?php echo __('comments.name_label') ?? 'Name'; ?> <span style="color:var(--pico-color-red, #d9534f);">*</span>
                <input type="text" id="pw-comment-name" name="name" required placeholder="<?php echo __('comments.name_label') ?? 'Name'; ?>">
            </label>
            
            <label for="pw-comment-email">
                <?php echo __('comments.email_label') ?? 'Email'; ?> <span style="color:var(--pico-color-red, #d9534f);">*</span>
                <input type="email" id="pw-comment-email" name="email" required placeholder="name@example.com">
            </label>
        </fieldset>

        <div style="position: absolute; left: -9999px;" aria-hidden="true">
            <label for="pw-comment-website">Website</label>
            <input type="text" id="pw-comment-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label for="pw-comment-text">
            <?php echo __('comments.text_label') ?? 'Comment'; ?> <span style="color:var(--pico-color-red, #d9534f);">*</span>
            <textarea id="pw-comment-text" name="text" rows="5" required placeholder="..."></textarea>
        </label>

        <button type="submit" id="pw-comment-submit">
            <?php echo __('comments.submit') ?? 'Submit Comment'; ?>
        </button>
    </form>
</section>
