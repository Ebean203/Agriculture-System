(function(){
  if (window.AgriToast) return; // singleton

  function createContainer(){
    var existing = document.querySelector('.agri-toast-container');
    if (existing) return existing;
    var el = document.createElement('div');
    el.className = 'agri-toast-container';
    document.body.appendChild(el);
    return el;
  }

  function show(type, message, opts){
    opts = opts || {};
    var duration = Math.max(1000, +opts.duration || 2000); // simple, short-lived
    var container = createContainer();

    var toast = document.createElement('div');
    toast.className = 'agri-toast agri-toast--' + (type || 'info');
    toast.textContent = String(message || ''); // simple text only
    container.appendChild(toast);

    // auto dismiss
    var timer = setTimeout(dismiss, duration);

    // click to dismiss early
    toast.addEventListener('click', function(){ clearTimeout(timer); dismiss(); });

    function dismiss(){
      toast.style.animation = 'agriToastSlideOut .15s ease-in forwards';
      setTimeout(function(){
        if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
        if (!container.children.length && container.parentNode) container.parentNode.removeChild(container);
      }, 140);
    }

    return dismiss;
  }

  window.AgriToast = {
    show: function(opts){ return show(opts.type || 'info', opts.message || '', opts); },
    success: function(message, duration){ return show('success', message, { duration: duration }); },
    error: function(message, duration){ return show('error', message, { duration: duration }); }
  };
})();
