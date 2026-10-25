<?php
/**
 * PureWiki Theme - Default Page Card
 *
 * One card of the page list block. Turned off fields are empty.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
?>
<article class="pw-pagecard">
    <?php if ($card['image']): ?>
        <a class="pw-pagecard-image" href="<?= $card['url'] ?>" tabindex="-1" aria-hidden="true"><img src="<?= $card['image'] ?>" alt="" loading="lazy"></a>
    <?php endif; ?>
    <div class="pw-pagecard-body">
        <h3 class="pw-pagecard-title"><a href="<?= $card['url'] ?>"><?= $card['title'] ?></a></h3>
        <?php if ($card['date']): ?>
            <time class="pw-pagecard-date" datetime="<?= $card['date_iso'] ?>"><?= $card['date'] ?></time>
        <?php endif; ?>
        <?php if ($card['teaser']): ?>
            <p class="pw-pagecard-teaser"><?= $card['teaser'] ?></p>
        <?php endif; ?>
        <?php if ($card['button']): ?>
            <a class="pw-pagecard-button" href="<?= $card['url'] ?>"><?= $card['button'] ?></a>
        <?php endif; ?>
    </div>
</article>
