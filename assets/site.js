/**
 * assets/site.js
 *
 * Small helpers so CSP can use script-src 'self' (no 'unsafe-inline'):
 * - Filter controls marked data-autosubmit submit their form on change.
 * - Advanced report dual-list column picker (report_advanced.php).
 */
(function () {
  'use strict';

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.getAttribute || el.getAttribute('data-autosubmit') === null) {
      return;
    }
    if (el.disabled) {
      return;
    }
    var form = el.form || (el.closest && el.closest('form'));
    if (form) {
      form.submit();
    }
  });

  function initReportColumnPicker() {
    var dual = document.getElementById('report-col-dual');
    var form = dual && dual.closest('form');
    var available = document.getElementById('report-cols-available');
    var selected = document.getElementById('report-cols-selected');
    if (!dual || !form || !available || !selected) {
      return;
    }
    dual.hidden = false;

    function selectedOptions(sel) {
      return Array.prototype.slice.call(sel.selectedOptions || [], 0);
    }

    function findOptgroup(select, label) {
      var groups = select.getElementsByTagName('optgroup');
      for (var i = 0; i < groups.length; i++) {
        if (groups[i].label === label) {
          return groups[i];
        }
      }
      var g = document.createElement('optgroup');
      g.label = label || 'Other';
      select.appendChild(g);
      return g;
    }

    function pruneEmptyOptgroups(select) {
      var groups = Array.prototype.slice.call(select.getElementsByTagName('optgroup'), 0);
      groups.forEach(function (g) {
        if (!g.querySelector('option')) {
          g.parentNode.removeChild(g);
        }
      });
    }

    function catalogInsert(option) {
      var section = option.getAttribute('data-section') || 'Other';
      var group = findOptgroup(available, section);
      group.appendChild(option);
    }

    function moveToSelected() {
      selectedOptions(available).forEach(function (opt) {
        selected.appendChild(opt);
        opt.selected = false;
      });
      pruneEmptyOptgroups(available);
    }

    function moveToAvailable() {
      selectedOptions(selected).forEach(function (opt) {
        catalogInsert(opt);
        opt.selected = false;
      });
    }

    function moveSelected(delta) {
      var opts = selectedOptions(selected);
      if (!opts.length) {
        return;
      }
      if (delta < 0) {
        opts.forEach(function (opt) {
          var prev = opt.previousElementSibling;
          if (prev) {
            selected.insertBefore(opt, prev);
          }
        });
      } else {
        for (var i = opts.length - 1; i >= 0; i--) {
          var opt = opts[i];
          var next = opt.nextElementSibling;
          if (next) {
            selected.insertBefore(next, opt);
          }
        }
      }
    }

    function prepareSubmit() {
      Array.prototype.forEach.call(selected.options, function (opt) {
        opt.selected = true;
      });
      Array.prototype.forEach.call(available.options, function (opt) {
        opt.selected = false;
      });
      selected.setAttribute('name', 'cols[]');
      available.removeAttribute('name');
    }

    var addBtn = document.getElementById('report-cols-add');
    var removeBtn = document.getElementById('report-cols-remove');
    var upBtn = document.getElementById('report-cols-up');
    var downBtn = document.getElementById('report-cols-down');
    if (addBtn) {
      addBtn.addEventListener('click', moveToSelected);
    }
    if (removeBtn) {
      removeBtn.addEventListener('click', moveToAvailable);
    }
    if (upBtn) {
      upBtn.addEventListener('click', function () { moveSelected(-1); });
    }
    if (downBtn) {
      downBtn.addEventListener('click', function () { moveSelected(1); });
    }

    available.addEventListener('dblclick', moveToSelected);
    selected.addEventListener('dblclick', moveToAvailable);
    form.addEventListener('submit', prepareSubmit);
  }

  initReportColumnPicker();
})();
