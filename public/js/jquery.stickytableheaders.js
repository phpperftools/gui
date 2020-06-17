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

(function ($) {
	$.StickyTableHeaders = function (el, options) {
		// To avoid scope issues, use 'base' instead of 'this'
		// to reference this class from internal events and functions.
		var base = this;

		// Access to jQuery and DOM versions of element
		base.$el = $(el);
		base.el = el;

		// Add a reverse reference to the DOM object
		base.$el.data('StickyTableHeaders', base);

		base.init = function () {
			base.options = $.extend({}, $.StickyTableHeaders.defaultOptions, options);

			base.$el.each(function () {
				var $this = $(this);
				$this.wrap('<div class="divTableWithFloatingHeader" style="position:relative"></div>');

				var originalHeaderRow = $('tr:first', this);
				originalHeaderRow.before(originalHeaderRow.clone());
				var clonedHeaderRow = $('tr:first', this);

				clonedHeaderRow.addClass('tableFloatingHeader');
				clonedHeaderRow.css('position', 'fixed');
				clonedHeaderRow.css('top', '0px');
				clonedHeaderRow.css('left', $this.css('margin-left'));
				clonedHeaderRow.css('display', 'none');

				originalHeaderRow.addClass('tableFloatingHeaderOriginal');

				// enabling support for jquery.tablesorter plugin
				$this.bind('sortEnd', function (e) { base.updateCloneFromOriginal(originalHeaderRow, clonedHeaderRow); });
			});

			base.updateTableHeaders();
			$(window).scroll(base.updateTableHeaders);
			$(window).resize(base.updateTableHeaders);
		};

		base.updateTableHeaders = function () {
			base.$el.each(function () {
				var $this = $(this);
				var $window = $(window);

				var fixedHeaderHeight = isNaN(base.options.fixedOffset) ? base.options.fixedOffset.height() : base.options.fixedOffset;

				var originalHeaderRow = $('.tableFloatingHeaderOriginal', this);
				var floatingHeaderRow = $('.tableFloatingHeader', this);
				var offset = $this.offset();
				var scrollTop = $window.scrollTop() + fixedHeaderHeight;
				var scrollLeft = $window.scrollLeft();

				if ((scrollTop > offset.top) && (scrollTop < offset.top + $this.height())) {
					floatingHeaderRow.css('top', fixedHeaderHeight + 'px');
					floatingHeaderRow.css('margin-top', 0);
					floatingHeaderRow.css('left', (offset.left - scrollLeft) + 'px');
					floatingHeaderRow.css('display', 'block');

					base.updateCloneFromOriginal(originalHeaderRow, floatingHeaderRow);
				}
				else {
					floatingHeaderRow.css('display', 'none');
				}
			});
		};

		base.updateCloneFromOriginal = function (originalHeaderRow, floatingHeaderRow) {
			// Copy cell widths and classes from original header
			$('th', floatingHeaderRow).each(function (index) {
				$this = $(this);
				var origCell = $('th', originalHeaderRow).eq(index);
				$this.removeClass().addClass(origCell.attr('class'));
				$this.css('width', origCell.width());
			});

			// Copy row width from whole table
			floatingHeaderRow.css('width', originalHeaderRow.width());
		};

		// Run initializer
		base.init();
	};

	$.StickyTableHeaders.defaultOptions = {
		fixedOffset: 0
	};

	$.fn.stickyTableHeaders = function (options) {
		return this.each(function () {
			(new $.StickyTableHeaders(this, options));
		});
	};

})(jQuery);
