<?php
/**
 * tobiju
 *
 * @link      https://github.com/tobiju/bookdown-bootswatch-templates for the canonical source repository
 * @copyright Copyright (c) 2015 Tobias Jüschke
 * @license   https://github.com/tobiju/bookdown-bootswatch-templates/blob/master/LICENSE.txt New BSD License
 */

$prev = $this->page->getPrev();
$parent = $this->page->getParent();
$next = $this->page->getNext();

if (!($copyright = $this->page->getCopyright())) {
    $copyright = '<a href="http://prooph-software.de/about.html">Imprint</a> | Powered by <a href="https://github.com/tobiju/bookdown-bootswatch-templates" title="Visit project to generate your own docs">Bookdown Bootswatch Templates</a>.';
}
?>
        </div>
    </div>
</div>

<footer>
    <div class="links">
        <div class="container">
            <div class="row">
                <div class="prev col-xs-6">
                    <?php if ($prev): ?>
                        <?= $this->anchorRaw($prev->getHref(), 'Previous'); ?>
                    <?php endif; ?>
                </div>
                <div class="next col-xs-6">
                    <?php if ($next): ?>
                        <?= $this->anchorRaw($next->getHref(), 'Next'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="copyright">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <p><?= $copyright; ?></p>
                </div>
            </div>
        </div>
    </div>
</footer>
