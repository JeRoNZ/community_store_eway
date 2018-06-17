<?php
defined('C5_EXECUTE') or die(_("Access Denied."));
extract($vars);
?>


<div class="form-group">
	<?= $form->label('Currency', t("Currency")); ?>
	<?= $form->select('ewayCurrency', $currencies, $ewayCurrency ? $ewayCurrency : 'NZD'); ?>
</div>

<div class="form-group">
	<label><?= t("API Key") ?></label>
	<input type="text" name="ewayAPIKey" value="<?= $ewayAPIKey ?>" class="form-control">
</div>

<div class="form-group">
	<label><?= t("Password") ?></label>
	<input type="text" name="ewayPassword" value="<?= $ewayPassword ?>" class="form-control">
</div>

<div class="form-group">
	<label><?= t("Use sandbox") ?></label>
	<?= $form->select('ewaySandbox', array('0' => t('Off'), '1' => t('On')), $ewaySandbox); ?>
</div>