// yield_monitoring_search.js
// Autocomplete farmer search for yield monitoring

document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('input[name="farmer_search"]');
    if (!input) return;

    let timeout = null;
    let dropdown = null;

    function closeDropdown() {
        if (dropdown) {
            dropdown.remove();
            dropdown = null;
        }
    }

    input.addEventListener('input', function() {
        closeDropdown();
        const query = input.value.trim();
        if (query.length < 2) return;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            fetch(`search_farmers.php?query=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.farmers.length) return;
                    dropdown = document.createElement('div');
                    dropdown.className = 'absolute z-50 bg-white border border-gray-300 rounded-lg shadow-lg mt-1 w-full';
                    data.farmers.forEach(farmer => {
                        const item = document.createElement('div');
                        item.className = 'px-4 py-2 cursor-pointer hover:bg-agri-light';
                        item.textContent = farmer.full_name + ' (' + farmer.barangay_name + ')';
                        item.dataset.farmerId = farmer.farmer_id;
                        item.addEventListener('mousedown', function(e) {
                            input.value = farmer.full_name;
                            // Set hidden input for farmer_id
                            let hidden = document.querySelector('input[name="farmer_id"]');
                            if (!hidden) {
                                hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = 'farmer_id';
                                input.form.appendChild(hidden);
                            }
                            hidden.value = farmer.farmer_id;
                            closeDropdown();
                        });
                        dropdown.appendChild(item);
                    });
                    input.parentNode.appendChild(dropdown);
                });
        }, 250);
    });

    input.addEventListener('blur', function() {
        setTimeout(closeDropdown, 200);
    });
});
