<?php
/**
 * @package	Hikashop bf_rbsglobalgateway Plugin
 * @version	3.0.2
 * @author	HikaShop
 * @copyright	(C) 2010-2023 HIKARI SOFTWARE. All rights reserved.
 * @license	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
defined('_JEXEC') or die('Restricted access');
?><?php
?>
<div class="hikashop_rbsworldpayXMLRedirect_end" id="hikashop_rbsworldpayXMLRedirect_end">
	<span id="hikashop_rbsworldpayXMLRedirect_end_message" class="hikashop_rbsworldpayXMLRedirect_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$method->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?>
	</span>
	<span id="hikashop_rbsworldpayXMLRedirect_end_spinner" class="hikashop_rbsworldpayXMLRedirect_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<form id="hikashop_rbsworldpayXMLRedirect_form" name="hikashop_rbsworldpayXMLRedirect_form" action="<?php echo htmlspecialchars($RBSRedirectURL, ENT_QUOTES);?>" method="post">
		<div id="hikashop_rbsworldpayXMLRedirect_end_image" class="hikashop_rbsworldpayXMLRedirect_end_image">
			<input id="hikashop_rbsworldpayXMLRedirect_end_button" <?php echo $method->payment_params->redirect_button; ?> type="submit" class="btn btn-primary" value="" name="" alt="Click to pay with Worldpay - it is fast, free and secure!" />
		</div>
		<?php
			foreach( $vars as $name => $value ) {
				echo '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
			}
			if (!empty($method->payment_params->showVars)) {
				echo '<pre>';
				print_r($vars);
				echo '</pre>';
			}
			else {
				$doc =& JFactory::getDocument();
				$doc->addScriptDeclaration("window.hikashop.ready( function() {document.getElementById('hikashop_rbsworldpayXMLRedirect_form').submit();});");
				hikaInput::get()->set('noform',1);
			}
		?>
	</form>
</div>
