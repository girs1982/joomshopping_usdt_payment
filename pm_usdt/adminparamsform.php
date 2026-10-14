<?php
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die();
?>
<div class="col100">
<fieldset class="adminform">
<table class="admintable" width="100%">
 <tr>
   <td style="width:250px;" class="key">Gateway URL</td>
   <td>
     <input type="text" class="inputbox form-control" name="pm_params[api_url]" size="45" value="<?php echo htmlspecialchars($params['api_url'], ENT_QUOTES); ?>" />
   </td>
 </tr>
 <tr>
   <td class="key">API key</td>
   <td>
     <input type="text" class="inputbox form-control" name="pm_params[api_key]" size="45" value="<?php echo htmlspecialchars($params['api_key'], ENT_QUOTES); ?>" />
   </td>
 </tr>
 <tr>
   <td class="key"><?php echo Text::_('JSHOP_TRANSACTION_END');?></td>
   <td>
     <?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_end_status]', 'class="inputbox custom-select" size="1" style="max-width:240px; display:inline-block"', 'status_id', 'name', $params['transaction_end_status']);?>
   </td>
 </tr>
 <tr>
   <td class="key"><?php echo Text::_('JSHOP_TRANSACTION_PENDING');?></td>
   <td>
     <?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_pending_status]', 'class="inputbox custom-select" size="1" style="max-width:240px; display:inline-block"', 'status_id', 'name', $params['transaction_pending_status']);?>
   </td>
 </tr>
 <tr>
   <td class="key"><?php echo Text::_('JSHOP_TRANSACTION_FAILED');?></td>
   <td>
     <?php print HTMLHelper::_('select.genericlist', $orders->getAllOrderStatus(), 'pm_params[transaction_failed_status]', 'class="inputbox custom-select" size="1" style="max-width:240px; display:inline-block"', 'status_id', 'name', $params['transaction_failed_status']);?>
   </td>
 </tr>
</table>
</fieldset>
</div>
<div class="clr"></div>