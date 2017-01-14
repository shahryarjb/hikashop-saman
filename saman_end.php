<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Hikashop
 * @subpackage 	trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');
?>
<div class="hikashop_saman_end" id="hikashop_saman_end">
	<span id="hikashop_saman_end_message" class="hikashop_saman_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$this->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?> 
	</span>
	<span id="hikashop_saman_end_spinner" class="hikashop_saman_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<?php echo $this->vars['saman']; ?>
</div>