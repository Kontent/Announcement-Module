<?php // no direct access
defined('_JEXEC') or die('Restricted Access');

$modclssfx = $params->get('moduleclass_sfx');

if (!empty($items)) :
?>
	<ul class="list announcements">
<?php foreach ($items as $item) : ?>
		<li>
<?php if ($item->thumbnail) : ?>
			<div class="thumb"><a href="<?php echo $item->article_link; ?>"><?php echo $item->thumbnail; ?></a></div>
<?php endif; ?>
			<div class="text-area">
				<a href="<?php echo $item->article_link; ?>"><?php echo $item->title; ?></a>
				<span class="date"><?php echo JHTML::date($item->date); ?></span>
				<p><?php echo $item->text; ?></p>
			</div>
		</li>
<?php endforeach; ?>
	</ul>
<?php
endif;
