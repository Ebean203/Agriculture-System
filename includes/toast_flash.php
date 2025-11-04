<?php
// Show toast notifications for common session keys, then clear them.
// Keys supported: success_message, error_message, success, error
$__toasts = [];
if (!empty($_SESSION['success_message'])) { $__toasts[] = ['type'=>'success','msg'=>$_SESSION['success_message']]; }
if (!empty($_SESSION['error_message']))   { $__toasts[] = ['type'=>'error','msg'=>$_SESSION['error_message']]; }
if (!empty($_SESSION['success']))        { $__toasts[] = ['type'=>'success','msg'=>$_SESSION['success']]; }
if (!empty($_SESSION['error']))          { $__toasts[] = ['type'=>'error','msg'=>$_SESSION['error']]; }

// Clear after capture
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['success'], $_SESSION['error']);

if (!empty($__toasts)):
?>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    if (!window.AgriToast) return;
    var items = <?php echo json_encode($__toasts, JSON_UNESCAPED_UNICODE); ?>;
    var delay = 0;
    items.forEach(function(t){
      setTimeout(function(){
        if (t.type === 'success') { AgriToast.success(t.msg); }
        else if (t.type === 'error') { AgriToast.error(t.msg, 2600); }
        else { AgriToast.show({ type: t.type, message: t.msg }); }
      }, delay);
      delay += 200; // slight staggering if multiple
    });
  });
</script>
<?php endif; ?>
