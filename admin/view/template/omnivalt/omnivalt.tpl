<?php
echo $header, $column_left;
?>
<div id='content'>

  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
      <button type="button" class="btn btn-default" data-toggle="modal" data-target="#myModal" title="<?=$text_courier;?>"><i class="fa fa fa-send-o	Try it
"></i></button>
        <a href="<?=$cancel;?>" data-toggle="tooltip" title="<?=$button_cancel;?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?=$heading_title;?></h1>(<?=$currentManifest;?>)
      
      <br><ul class="breadcrumb">
        <?php foreach($breadcrumbs as $breadcrumb):?>
        <li><a href="<?=$breadcrumb['href'];?>"> <?=$breadcrumb['text'];?></a></li>
        <?php endforeach;?>
      </ul>
    </div>
  </div>


  <div class="container-fluid">
    <div class="panel panel-default">
              <div class="panel-body">
          <ul class="nav nav-tabs">
 <li class="active"><a href="#tab-general" data-toggle="tab"><?=$text_new_orders;?></a></li>
            <li><a href="#tab-data" data-toggle="tab"><?=$text_awaiting;?></a></li>
            <li><a href="#tab-sent-orders" data-toggle="tab"><?=$text_completed;?></a></li>
            <li><a href="#tab-search-orders" data-toggle="tab"><?=$text_search;?></a></li>

          </ul>
          <div class="tab-content">

            <div class="tab-pane active" id="tab-general">
              <div class="tab-content">
                   <?php if(!empty($newOrders)) { ?>
              <form method="POST" action="<?=$labels;?>" id="new<?=$currentManifest;?>"  target='_blank'>
                    <button type='submit' class='btn btn-default btn-sm' form="new<?=$currentManifest;?>" name='print' class='omniv' value='manifest'><?=$text_manifest;?></button>
                    <button type='submit' class='btn btn-default btn-sm' form="new<?=$currentManifest;?>" name='print' value='labels'><?=$text_labels;?></button>

     <table class='table table-bordered table-hover'>

    <thead>
        <th width='5%'>id</th>
        <th width='15%'><?=$text_customer;?></th>
        <th width='15%'><?=$text_tracking_num;?></th>
        <th width='15%'><?=$text_date;?></th>
        <th width='15%'><?=$text_total;?></th>
        <th width='15%'><?=$text_labels;?></th>
    </thead>
<?php foreach($newOrders as $nOrder) {?>

<tr>
        <td class='left'><?=$nOrder['order_id'];?></td>
        <td width='15%'><a href="<?php echo $client.'&order_id='.$nOrder['order_id'];?>" target="_blank"><?=$nOrder['full_name'];?></a></td>
        <td width='15%'>
               <?php
        $variable = json_decode($nOrder['tracking']);
        $numb = intval($nOrder['labelscount']);
        if($variable != null and $numb > count($variable))
            $numb = count($variable);
        for($i=0; $i<$numb; $i++) {
            echo $variable[$i].'<br>';
        }
        ?>
        </td>
        <td width='15%'><?=$nOrder['date_modified'];?></td>
        <td width='15%'><?=$nOrder['total'];?></td>
        <td width='15%'>
        <a href="<?=$genLabels;?>&order_id=<?=$nOrder['order_id'];?>" target="_blank"><?=$generate_label;?></a>
        <?php if($nOrder['tracking'] == null) {?>
        <a href="<?=$skip;?>&order_id=<?=$nOrder['order_id'];?>"><?=$text_skip_order;?></a><?php }?>
        <input type='hidden' name='selected[]' value="<?=$nOrder['order_id'];?>">
        <?php if($nOrder != null) { ?>
        <input type='hidden' name='manifest' value="<?=$nOrder['manifest'];?>">
        <?php } else { ?>
        <input type='hidden' name='new' value="new">
        <?php } ?>
        </td>
    </tr>

    <?php } ?> 
        </table>
    </form>
    <?php } else { print $text_new_zero; }?>

              </div>
            </div>
            <div class="tab-pane" id="tab-data">
   <div class="tab-content">
   <?php if(!empty($skipped)) { ?>
     <table class='table table-bordered table-hover'>
    <thead>
        <th width='5%'>id</th>
        <th width='15%'><?=$text_customer;?></th>
        <th width='15%'><?=$text_tracking_num;?></th>
        <th width='15%'><?=$text_date;?></th>
        <th width='15%'><?=$text_total;?></th>
        <th width='15%'><?=$text_labels;?></th>
    </thead>
    <?php  foreach($skipped as $nOrder) {?>
<tr>
        <td class='left'><?=$nOrder['order_id'];?></td>
        <td width='15%'><a href="<?php echo $client.'&order_id='.$nOrder['order_id'];?>" target="_blank"><?=$nOrder['full_name'];?></a></td>
        <td width='15%'></td>
        <td width='15%'><?=$nOrder['date_modified'];?></td>
        <td width='15%'><?=$nOrder['total'];?></td>
        <td width='15%'>
                <?php if($nOrder['manifest'] == -1){?>
            <a href="<?=$cancelSkip;?>&order_id=<?=$nOrder['order_id'];?>"><?=$text_add_order;?></a>
        <?php } ?>
        </td>
    </tr>
<?php }} else print $text_skipped_zero; ?>
    </table>
                </div>
              </div>
 
 <!-- Sent orders -->
            <div class="tab-pane" id="tab-sent-orders">
              <div class="table-responsive">

<?php  foreach($orders as $order) {
     if((isset($manifest) && $order['manifest'] != $manifest) OR $newPage == null) {
$newPage = true;
                //print"<tr><td colspan='5'><hr></td><td><input type='submit' value='Manifestas' class='btn btn-primary btn-sm'></form><form method='POST' action=".$labels." target='_blank'></td><tr>";
     ?>
     </table>
     </form>
     <form method="POST" action="<?=$labels;?>" id="frm<?=$order['manifest'];?>"  target='_blank'>

    <button type='submit' form="frm<?=$order['manifest'];?>" class='btn btn-default btn-sm' name='print' class='omniv' value='manifest'><?=$text_manifest;?></button>
    <button type='submit' form="frm<?=$order['manifest'];?>" class='btn btn-default btn-sm' name='print' value='labels'><?=$text_labels;?></button>

    <table class='table table-bordered table-hover'>
    <thead>
        <th width='5%'>id</th>
        <th width='15%'><?=$text_customer;?></th>
        <th width='15%'><?=$text_tracking_num;?></th>
        <th width='15%'><?=$text_date;?></th>
        <th width='15%'><?=$text_total;?></th>
        <th width='15%'><?=$text_labels;?></th>
    </thead>
     <?php
     }
            $manifest = $order['manifest'];
    ?>
    <tr>
        <td><?=$order['order_id'];?></td>
        <td><a href="<?php echo $client.'&order_id='.$order['order_id'];?>"><?=$order['full_name'];?></a></td>
        <td>
        <?php 
        $variable = json_decode($order['tracking']);
        $numb = intval($order['labelscount']);
        if($numb > count($variable))
            $numb = count($variable);
        for($i=0; $i<$numb; $i++) {
            echo $variable[$i].'<br>';
        }
        ?>
        </td>
        <td><?=$order['date_modified'];?></td>
        <td><?=$order['total'];?></td>
        <?php /*<td><?=$order['labels'];?></td>*/?>
        <td ><?php /*echo $order['manifest']; */ ?>
        <a href="<?=$genLabels;?>&order_id=<?=$order['order_id'];?>" target="_blank"><?=$generate_label;?></a>
 
        <input type='hidden' name='selected[]' value="<?=$order['order_id'];?>">
        <input type='hidden' name='manifest' value="<?=$order['manifest'];?>">
        </td>
    </tr>

<?php 
}?></form></td>
    </table>
    <div class="text-center">
        <?php echo $pagination; ?>
    </div>
    </div>
    
    </div>
        <div class="tab-pane" id="tab-search-orders">
        <div class="tab-content">
        <form action="<?= $search;?>" method="POST">

                        <div class="well">
          <div class="row">
            <div class="col-sm-4">
              <div class="form-group">
                <label class="control-label" for="input-tracking_nr"><?=$text_tracking_num;?></label>
                <input type="text" name="tracking_nr" value="" placeholder="<?=$text_tracking_num;?>" class="form-control">
              </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                <label class="control-label" for="input-customer"><?=$text_customer;?></label>
                <input type="text" name="customer" value="" placeholder="<?=$text_customer;?>" id="input-customer" class="form-control" autocomplete="off"><ul class="dropdown-menu"></ul>
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                <label class="control-label" for="input-date-added"><?=$text_date;?></label>
                <div class="input-group date">
                  <input type="text" name="date_added" value="" placeholder="Data" data-date-format="YYYY-MM-DD" id="input-date-added" class="form-control">
                  <span class="input-group-btn">
                  <button type="button" class="btn btn-default"><i class="fa fa-calendar"></i></button>
                  </span>
                </div>
              </div>
              <button type="button" id="button-search" class="btn btn-primary pull-right"><i class="fa fa-filter"></i> Filter</button>
            </div>
          </div>
        </div></form>

        <table class='table table-bordered table-hover'>
        <thead>
            <th width='5%'>id</th>
        <th width='15%'><?=$text_customer;?></th>
        <th width='15%'><?=$text_tracking_num;?></th>
        <th width='15%'><?=$text_date;?></th>
        <th width='15%'><?=$text_total;?></th>
        <th width='15%'><?=$text_labels;?></th>
        </thead>
        <tbody id="searchTable">
        <th colspan='6'><?=$text_start_search;?></th>
        </tbody>
        </table>
        </div>
        </div>
</div>
</div>


              </div>
            </div>
<!--  Modal  -->
<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog modal-lg">
    <!-- Modal content-->
    <div class="modal-content">
    <form class="form-horizontal">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><?= $text_courier_call; ?></h4>
      </div>
      <div class="modal-body">
            <div class="alert alert-info">
                    <strong><?= $text_omniva_important; ?></strong> <?= $text_latest_courier_call;?>
                    <br />
                    <strong><?= $text_eshop_settings;?></strong> <?= $text_eshop_settings_p;?>
            </div>
            <h4><?= $text_omniva_data_send;?><h4>
            <b><?=$entry_sender_name;?>:</b> <?= $sender;?><br>
            <b><?=$entry_sender_phone;?>:</b> <?= $phone;?><br>
            <b><?=$entry_sender_postcode;?>:</b> <?= $postcode;?><br>
            <b><?=$entry_sender_address;?>:</b> <?= $address;?><br>
      </div>
      <div class="modal-footer">
            <button type="submit"  id="requestOmnivaltQourier" class="btn btn-default"><?=$button_save;?></button>
            <button type="button" class="btn btn-default" data-dismiss="modal"><?=$button_cancel;?></button>
      </div>
    </form>
    </div>
  </div>
</div>
<script>
  url = location.href;
sent = url.slice(-3);
if (sent != 'undefined') { 
   if (sent == 'imp') { // sent is equal to yes
 
     $('[href="#tab-sent-orders"]').trigger('click');
   }
}
$(document).ready(function() {
 $('#requestOmnivaltQourier').on('click', function(e) {
     e.preventDefault();
	$.ajax({
		url: 'index.php?route=omnivalt/omnivalt/callCarrier&token=<?php echo $token; ?>',
		type: 'get',
    data:  {'labelsCount': 5, 
            'order_id': 5},
	
		success: function(data) {
			
            if(data == 'got_request'){
                $('.modal-body').append('<div class="alert alert-success" id="remove">\
                 <strong>Baigta!</strong> Pranešimas sėkmingai išsiųstas.\
                </div>');
            } else {
                $('.modal-body').append('<div class="alert alert-danger" id="remove">\
                 <strong>Deja!</strong> klaidingas atsakymas.\
                </div>');
            }

        setTimeout(function(){
            $('#remove').remove();
            $('#myModal').modal('hide');
            }, 3000);
                
		},
    		error: function(xhr, ajaxOptions, thrownError) {
			/* alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);*/
		}
	});
 });

    $('#button-search').on('click', function() {
        var tracking = $('input[name="tracking_nr"]').val();
        var customer = $('input[name="customer"]').val();
        var dateAdd = $('input[name="input-date-added"]').val();

        	$.ajax({
		url: 'index.php?route=omnivalt/omnivalt/searchOmnivaOrders&token=<?php echo $token; ?>',
		type: 'post',
        dataType: 'json',
        data: $('input[name="tracking_nr"], input[name="customer"], input[id="input-date-added"]'),
        beforeSend: function() {
            $('#searchTable').empty();
        },
		success: function(data) {
			
    

        for(gotOrder of data){

            $('#searchTable').append("<tr><td class='left'>"+gotOrder['order_id']+"</td>\
                <td> <a href='index.php?route=sale/order/info&token=<?php echo $token; ?>&order_id="+gotOrder['order_id']+"' target='_blank'>"+gotOrder['full_name']+"</a></td>\
                <td> "+gotOrder['tracking']+"</a></td>\
                <td>"+gotOrder['date_modified']+"</td>\
                <td>"+gotOrder['total']+"</td>\
                <td> <a href='index.php?route=extension/shipping/omnivalt/labels&token=<?php echo $token; ?>&order_id="+gotOrder['order_id']+"' target='_blank'>Generuoti lipdukus</a></td>\
            </tr>");
        }
        if(data.length<1)
            $('#searchTable').append("<tr><td colspan='6'>...</td>");   
		},
    		error: function(xhr, ajaxOptions, thrownError) {
			/* alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);*/
		}
	});
    });
});
</script>
<script src="view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.js" type="text/javascript"></script>
<link href="view/javascript/jquery/datetimepicker/bootstrap-datetimepicker.min.css" type="text/css" rel="stylesheet" media="screen">
<script type="text/javascript"><!--
$('.date').datetimepicker({
	pickTime: false
});
//--></script>
<?php
echo $footer;
