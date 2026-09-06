(function () {
    'use strict';

    var root = document.querySelector('.av-bp');
    if (!root) {
        return;
    }

    document.body.classList.add('av-bulk-pricing-manager');

    var selectAll = document.getElementById('av-bp-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            root.querySelectorAll('.av-bp-item-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    var operation = document.getElementById('av-bp-operation');
    var valueWrap = document.getElementById('av-bp-operation-value-wrap');
    var valueInput = document.getElementById('av-bp-operation-value');

    function updateOperationValue() {
        if (!operation || !valueWrap || !valueInput) {
            return;
        }
        var isCatalogSync = operation.value === 'sync_catalog';
        valueWrap.style.display = isCatalogSync ? 'none' : '';
        valueInput.required = !isCatalogSync;
        if (isCatalogSync) {
            valueInput.value = '';
        }
    }

    if (operation) {
        operation.addEventListener('change', updateOperationValue);
        updateOperationValue();
    }

    var operationForm = document.getElementById('av-bp-operation-form');
    if (operationForm) {
        operationForm.addEventListener('submit', function (event) {
            if (!root.querySelector('.av-bp-item-checkbox:checked')) {
                event.preventDefault();
                window.alert('Selecione pelo menos um registro.');
            }
        });
    }

    root.querySelectorAll('.av-bp-submit-once').forEach(function (button) {
        button.closest('form').addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Processando...';
        });
    });
}());

