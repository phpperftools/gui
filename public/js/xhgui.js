/*
 * Original code Copyright 2013 Mark Story & Paul Reinheimer
 * Changes Copyright Grzegorz Drozd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

window.Xhgui = window.Xhgui || {};

Xhgui.tableSort = function(tables) {
    tables.stickyTableHeaders();
    tables.tablesorter({
        textExtraction: function(node) {
            if (node.className.match(/text/)) {
                return node.innerText;
            }
            var text = node.innerText || node.textContent;
            return '' + parseInt(text.replace(/,/g, ''), 10);
        }
    });
};

// Utilitarian DOM behavior.
$(document).ready(function () {
    $('.tip').tooltip();

    var tables = $('.table-sort');
    Xhgui.tableSort(tables);

    $('.datepicker').datepicker();
    $('.dropdown-toggle').dropdown();

    $("a[data-toggle=popover]")
        .popover()
        .click(function(e) {
            e.preventDefault()
        });

    $('#handlerSelect').on('change', function () {
        window.location = replaceQueryParam('handler', $(this).val(), window.location.search);
    });

    // Bind events for expandable search forms.
    var searchForm = $('.search-form'),
        searchExpand = $('.search-expand');

    searchExpand.on('click', function () {
        searchExpand.fadeOut('fast', function () {
            searchForm.slideDown('fast');
        });
        return false;
    });

    $('.search-collapse').on('click', function () {
        searchForm.slideUp('fast', function () {
            searchExpand.show();
        });
        return false;
    });


    $('[data-toggle="table-settings"]').each(function (i, el) {
        var $el = $(el);
        tableSettings($("#"+$el.data('target')), $el, $el.data('cookie-name'));
    });
});

$( document ).ajaxComplete(function () {
    $('.tip').tooltip();
});



function replaceQueryParam(param, newval, search) {
    var regex = new RegExp("([?;&])" + param + "[^&;]*[;&]?");
    var query = search.replace(regex, "$1").replace(/&$/, '');

    return (query.length > 2 ? query + "&" : "?") + (newval ? param + "=" + newval : '');
}

function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+ d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

function tableSettings(popover, popoverTrigger, cookieName) {
    var applyButton = popover.find('.btn-primary');
    var resetButton = popover.find('.btn-reset');
    popoverTrigger.on('click', function () {
        popover.toggle();
        var width = popoverTrigger.outerWidth();
        var height = popover.height();
        var buttonPosition = popoverTrigger.offset();
        popover.css({
            right: width + 20,
            top: 10+buttonPosition.top-20
        });
        $(".arrow", popover).css({
            top: buttonPosition.top-100
        });
        popover.find('input').on('change', function (e) {
            var t = $(e.target);
            if (t.attr("checked")) {
                $('.'+t.val()).removeClass('hidden').show();
            } else {
                $('.'+t.val()).removeClass('hidden').hide();
            }
            setCookie(cookieName, popover.find('input').serialize(), 90);
        });

        return false;
    });

    applyButton.click(function(e) {
        popover.toggle();
        e.preventDefault();
    });
    resetButton.click(function(e) {
        popover.toggle();
        setCookie(cookieName, "", -19);
        e.preventDefault();
        window.location.reload();
    });
}

