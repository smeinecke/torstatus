(function() {
    'use strict';

    // --- Search box ---
    var searchbox = document.getElementById('searchbox');
    if (searchbox) {
        if (searchbox.value === 'search for a router') {
            searchbox.style.color = 'gray';
        }

        searchbox.addEventListener('focus', function() {
            if (this.value === 'search for a router') {
                this.style.color = 'black';
                this.value = '';
            }
        });
    }

    var searchSubmit = document.getElementById('search-submit');
    if (searchSubmit) {
        searchSubmit.addEventListener('click', function(e) {
            e.preventDefault();
            document.forms.search.submit();
        });
    }

    var rowsPerPageSelect = document.getElementById('rows-per-page-select');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }

    // --- Infobar ---
    var infobar = document.getElementById('infobar');
    var expandcollapseLink = document.getElementById('expandcollapse-link');
    var expandcollapseImg = document.getElementById('expandcollapse-img');
    var expandcollapseText = document.getElementById('expandcollapse-text');

    function expand_infobar() {
        if (infobar) infobar.style.display = 'block';
        if (expandcollapseImg) expandcollapseImg.src = '/img/infobarcollapse.png';
        if (expandcollapseText) expandcollapseText.textContent = 'Hide Advanced Options';
    }

    function collapse_infobar() {
        if (infobar) infobar.style.display = 'none';
        if (expandcollapseImg) expandcollapseImg.src = '/img/infobarexpand.png';
        if (expandcollapseText) expandcollapseText.textContent = 'Show Advanced Options';
    }

    if (expandcollapseLink) {
        expandcollapseLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (infobar && infobar.style.display === 'none') {
                expand_infobar();
            } else {
                collapse_infobar();
            }
        });
    }

    if (infobar) collapse_infobar();

    // --- Index page toggles ---
    var toggleConfig = {
        'anss': {
            show: 'Show Aggregate Network Statistic Summary',
            hide: 'Hide Aggregate Network Statistic Summary'
        },
        'nsos': {
            show: 'Show Network Status Opinion Source',
            hide: 'Hide Network Status Opinion Source'
        },
        'caqo': {
            show: 'Show Custom / Advanced Query Options',
            hide: 'Hide Custom / Advanced Query Options'
        },
        'lgnd': {
            show: 'Show Table Legend',
            hide: 'Hide Table Legend'
        },
        'asd': {
            show: 'Show Application Server Details',
            hide: 'Hide Application Server Details'
        }
    };

    function showSection(key) {
        var cfg = toggleConfig[key];
        if (!cfg) return;
        var table = document.getElementById(key + 'Table');
        var link = document.getElementById(key + 'TableLink');
        if (table && link) {
            table.style.display = 'table';
            link.innerHTML = cfg.hide + ' <img src="img/blackinfobarcollapse.png" class="infobarbutton"/>';
        }
    }

    function toggleSection(key) {
        var cfg = toggleConfig[key];
        if (!cfg) return;
        var table = document.getElementById(key + 'Table');
        var link = document.getElementById(key + 'TableLink');
        if (!table || !link) return;

        if (table.style.display === 'none') {
            showSection(key);
        } else {
            table.style.display = 'none';
            link.innerHTML = cfg.show + ' <img src="img/blackinfobarexpand.png" class="infobarbutton"/>';
        }
    }

    document.querySelectorAll('a[data-toggle]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSection(this.getAttribute('data-toggle'));
        });
    });

    document.querySelectorAll('a[data-toggle-show]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            var key = this.getAttribute('data-toggle-show');
            var table = document.getElementById(key + 'Table');
            if (table) {
                e.preventDefault();
                showSection(key);
                table.scrollIntoView();
            }
        });
    });

    Object.keys(toggleConfig).forEach(function(key) {
        var table = document.getElementById(key + 'Table');
        var link = document.getElementById(key + 'TableLink');
        var cfg = toggleConfig[key];
        if (table && link) {
            table.style.display = 'none';
            link.innerHTML = cfg.show + ' <img src="img/blackinfobarexpand.png" class="infobarbutton"/>';
        }
    });

    var hashMap = {
        '#Stats': 'anss',
        '#TorServer': 'nsos',
        '#CustomQuery': 'caqo',
        '#AppServer': 'asd'
    };
    if (hashMap[window.location.hash]) {
        showSection(hashMap[window.location.hash]);
    }
})();
