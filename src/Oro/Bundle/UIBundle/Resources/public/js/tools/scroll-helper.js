define(function(require, exports, module) {
    'use strict';

    const $ = require('jquery');
    const _ = require('underscore');
    const tools = require('oroui/js/tools');
    const mediator = require('oroui/js/mediator');
    let config = require('module-config').default(module.id);
    require('jquery-ui/position');

    config = _.extend({
        scrollableContainerSelector: 'html'
    }, config);

    let _scrollTimer;

    const getScrollY = (function(scroller) {
        if (window.pageYOffset !== undefined) {
            return function() {
                return window.pageYOffset;
            };
        }

        if (window.scrollTop !== undefined) {
            return function() {
                return window.scrollTop;
            };
        }

        return function() {
            return scroller.scrollTop;
        };
    })(document.documentElement || document.body.parentNode || document.body);

    const scrollHelper = {
        /**
         * Height of header on mobile devices
         */
        MOBILE_HEADER_HEIGHT: 54,

        /**
         * Height of header on mobile devices
         */
        MOBILE_POPUP_HEADER_HEIGHT: 44,

        /**
         * Cached scrollbarWidth value
         */
        _scrollbarWidth: -1,

        /**
         * Select global scrollable container
         */
        _scrollableContainerSelector: config.scrollableContainerSelector,

        /**
         * Store scroll position
         */
        _scrollState: null,

        /**
         * Timeout (ms) for stop scrolling
         */
        _scrollTimeout: 50,

        /**
         * Store scroll direction (1 - down, -1 - up, 0 - no scrolling)
         */
        _scrollDirection: null,

        /**
         * Disable/Enable scroll state
         */
        _isBodyTouchScrollDisabled: false,

        _passiveEventSupported: void 0,

        /**
         * Disable body scroll on touch devices
         * @returns {boolean}
         */
        disableBodyTouchScroll: function() {
            if (this._isBodyTouchScrollDisabled) {
                return false;
            }

            this._scrollState = this.getScrollY();

            $(this._scrollableContainerSelector)
                .addClass('disable-touch-scrolling')
                .css('height', window.innerHeight);

            $(document)
                .off('touchmove.disableScroll')
                .on('touchmove.disableScroll', _.bind(this._preventMobileScrolling, this));

            this._isBodyTouchScrollDisabled = true;
        },

        /**
         * Enable body scroll on touch devices
         * @returns {boolean}
         */
        enableBodyTouchScroll: function() {
            if (!this._isBodyTouchScrollDisabled) {
                return false;
            }

            $(this._scrollableContainerSelector)
                .removeClass('disable-touch-scrolling')
                .css('height', '');

            $(document).off('touchmove.disableScroll');

            window.scrollTo(0, this._scrollState);

            this._isBodyTouchScrollDisabled = false;
            this._scrollState = null;
        },

        /**
         * Block touch move event propagation on body
         * @param {jQueryEvent} event
         * @private
         */
        _preventMobileScrolling: function(event) {
            let isTouchMoveAllowed = true;
            let target = event.target;

            while (target !== null) {
                if (target.classList && target.classList.contains('disable-scrolling')) {
                    isTouchMoveAllowed = false;
                    break;
                }
                target = target.parentNode;
            }

            if (!isTouchMoveAllowed) {
                event.preventDefault();
            }
        },

        /**
         * Remove bounce effect, when scroll overflow viewport
         * @param {jQueryEvent} event
         */
        removeIOSRubberEffect: function(event) {
            const element = event.currentTarget;
            const top = element.scrollTop;
            const totalScroll = element.scrollHeight;
            const currentScroll = top + element.offsetHeight;

            if (top === 0) {
                element.scrollTop = 1;
            } else if (currentScroll === totalScroll) {
                element.scrollTop = top - 1;
            }
        },
        /**
         * Try to calculate the scrollbar width for your browser/os
         * @return {Number}
         */
        scrollbarWidth: function() {
            if (this._scrollbarWidth === -1) {
                this._scrollbarWidth = $.position.scrollbarWidth();
            }
            return this._scrollbarWidth;
        },

        /**
         * Returns true if el has visible scroll
         */
        hasScroll: function(el, a) {
            if ($(el).css('overflow') === 'hidden') {
                return false;
            }
            const scroll = (a && a === 'left') ? 'scrollLeft' : 'scrollTop';
            if (el[scroll] > 0) {
                return true;
            }
            el[scroll] = 1;
            const has = (el[scroll] > 0);
            el[scroll] = 0;
            return has;
        },

        /**
         * Cached documentHeight value
         */
        _documentHeight: -1,

        /**
         * Returns actual documentHeight
         * @return {Number}
         */
        documentHeight: function() {
            if (this._documentHeight === -1) {
                this._documentHeight = $(document).height();
            }
            return this._documentHeight;
        },

        /**
         * Returns visible rect of DOM element
         *
         * @param el
         * @param {{top: number, left: number, bottom: number, right: number}} increments for each initial rect side
         * @param {boolean} forceInvisible if true - function will return initial rect when element is out of screen
         * @param {Function} onAfterGetClientRect - callback called after each getBoundingClientRect
         * @returns {{top: number, left: number, bottom: number, right: number}}
         */
        getVisibleRect: function(el, increments, forceInvisible, onAfterGetClientRect) {
            increments = increments || {};
            _.defaults(increments, {
                top: 0,
                left: 0,
                bottom: 0,
                right: 0
            });
            let current = el;
            let midRect = this.getEditableClientRect(current);
            if (onAfterGetClientRect) {
                onAfterGetClientRect(current, midRect);
            }
            let borders;
            const resultRect = {
                top: midRect.top + increments.top,
                left: midRect.left + increments.left,
                bottom: midRect.bottom + increments.bottom,
                right: midRect.right + increments.right
            };
            if (
                (resultRect.top === 0 && resultRect.bottom === 0) || // no-data block is shown
                (resultRect.top > this.documentHeight() && forceInvisible)
            ) {
                // no need to calculate anything
                return resultRect;
            }
            current = current.parentNode;
            while (current && current.getBoundingClientRect) {
                /**
                 * Equals header height. Cannot calculate dynamically due to issues on ipad
                 */
                if (resultRect.top < this.MOBILE_HEADER_HEIGHT && tools.isMobile()) {
                    if (current.id === 'central-panel' && !$(document.body).hasClass('input-focused')) {
                        resultRect.top = this.MOBILE_HEADER_HEIGHT;
                    } else if (current.className.split(/\s+/).indexOf('widget-content') !== -1) {
                        resultRect.top = this.MOBILE_POPUP_HEADER_HEIGHT;
                    }
                }

                midRect = this.getFinalVisibleRect(current, onAfterGetClientRect);
                borders = $.fn.getBorders(current);

                const style = window.getComputedStyle(current);
                if (style.overflowX !== 'visible' || style.overflowY !== 'visible') {
                    if (resultRect.top < midRect.top + borders.top) {
                        resultRect.top = midRect.top + borders.top;
                    }
                    if (resultRect.bottom > midRect.bottom - borders.bottom) {
                        resultRect.bottom = midRect.bottom - borders.bottom;
                    }
                    if (resultRect.left < midRect.left + borders.left) {
                        resultRect.left = midRect.left + borders.left;
                    }
                    if (resultRect.right > midRect.right - borders.right) {
                        resultRect.right = midRect.right - borders.right;
                    }
                }
                current = current.parentNode;
            }

            if (resultRect.top < 0) {
                resultRect.top = 0;
            }

            return resultRect;
        },

        getFinalVisibleRect: function(current, onAfterGetClientRect) {
            const rect = this.getEditableClientRect(current);
            if (onAfterGetClientRect) {
                onAfterGetClientRect(current, rect);
            }

            const border = $.fn.getBorders(current);
            const vScrollIsVisible = (current.offsetWidth - border.left - border.right) > current.clientWidth;
            const hScrollIsVisible = (current.offsetHeight - border.top - border.bottom) > current.clientHeight;

            if (hScrollIsVisible && current.scrollHeight > current.clientHeight) {
                rect.bottom -= this.scrollbarWidth();
            }
            if (vScrollIsVisible && current.scrollWidth > current.clientWidth) {
                rect.right -= this.scrollbarWidth();
            }
            return rect;
        },

        getEditableClientRect: function(el) {
            const rect = el.getBoundingClientRect();
            return {
                top: rect.top,
                left: rect.left,
                bottom: rect.bottom,
                right: rect.right
            };
        },

        isCompletelyVisible: function(el, onAfterGetClientRect) {
            const rect = this.getEditableClientRect(el);
            if (onAfterGetClientRect) {
                onAfterGetClientRect(el, rect);
            }
            if (rect.top === rect.bottom || rect.left === rect.right) {
                return false;
            }
            const visibleRect = this.getVisibleRect(el, null, false, onAfterGetClientRect);
            return visibleRect.top === rect.top &&
                visibleRect.bottom === rect.bottom &&
                visibleRect.left === rect.left &&
                visibleRect.right === rect.right;
        },

        scrollIntoView: function(el, onAfterGetClientRect, verticalGap, horizontalGap) {
            if (this.isCompletelyVisible(el, onAfterGetClientRect)) {
                return {vertical: 0, horizontal: 0};
            }

            const rect = this.getEditableClientRect(el);
            if (onAfterGetClientRect) {
                onAfterGetClientRect(el, rect);
            }
            if (rect.top === rect.bottom || rect.left === rect.right) {
                return {vertical: 0, horizontal: 0};
            }
            const visibleRect = this.getVisibleRect(el, null, false, onAfterGetClientRect);
            const scrolls = {
                vertical: rect.top !== visibleRect.top ? visibleRect.top - rect.top
                    : (rect.bottom !== visibleRect.bottom ? visibleRect.bottom - rect.bottom : 0),
                horizontal: rect.left !== visibleRect.left ? visibleRect.left - rect.left
                    : (rect.right !== visibleRect.right ? visibleRect.right - rect.right : 0)
            };

            if (verticalGap && scrolls.vertical) {
                scrolls.vertical += verticalGap * Math.sign(scrolls.vertical);
            }

            if (horizontalGap && scrolls.horizontal) {
                scrolls.horizontal += horizontalGap * Math.sign(scrolls.horizontal);
            }

            return this.applyScrollToParents(el, scrolls);
        },

        applyScrollToParents: function(el, scrolls) {
            if (!scrolls.horizontal && !scrolls.vertical) {
                return scrolls;
            }

            // make a local copy to don't change initial object
            scrolls = _.extend({}, scrolls);

            $(el).parents().each(function() {
                const $this = $(this);
                if (scrolls.horizontal !== 0) {
                    switch ($this.css('overflowX')) {
                        case 'auto':
                        case 'scroll':
                            if (this.clientWidth < this.scrollWidth) {
                                const oldScrollLeft = this.scrollLeft;
                                this.scrollLeft = this.scrollLeft - scrolls.horizontal;
                                scrolls.horizontal += this.scrollLeft - oldScrollLeft;
                            }
                            break;
                        default:
                            break;
                    }
                }
                if (scrolls.vertical !== 0) {
                    switch ($this.css('overflowY')) {
                        case 'auto':
                        case 'scroll':
                            if (this.clientHeight < this.scrollHeight) {
                                const oldScrollTop = this.scrollTop;
                                this.scrollTop = this.scrollTop - scrolls.vertical;
                                scrolls.vertical += this.scrollTop - oldScrollTop;
                            }
                            break;
                        default:
                            break;
                    }
                }
            });

            return scrolls;
        },

        getScrollY: getScrollY,

        _setScrollDirection: function() {
            if (this._isBodyTouchScrollDisabled) {
                return;
            }

            clearTimeout(_scrollTimer);

            const scrollY = this.getScrollY();
            const direction = Math.sign(scrollY - this._scrollState);

            if (direction && direction !== this._scrollDirection) {
                mediator.trigger('scroll:direction:change', direction);
            }

            this._scrollDirection = direction;
            this._scrollState = scrollY;

            _scrollTimer = setTimeout(function() {
                mediator.trigger('scroll:direction:change', 0);
            }, this._scrollTimeout);
        },

        /**
         * Detects support for the passive option to addEventListener
         */
        isPassiveEventSupported: function() {
            if (this._passiveEventSupported !== void 0) {
                return this._passiveEventSupported;
            }

            let support = false;

            try {
                const opts = Object.defineProperty({}, 'passive', {
                    get: function() {
                        support = true;
                    }
                });
                const fn = function() {};

                window.addEventListener('checkPassiveEvent', fn, opts);
                window.removeEventListener('checkPassiveEvent', fn, opts);
            } catch (e) {}

            return this._passiveEventSupported = support;
        }
    };

    // reset document height cache on resize
    $(window).bindFirst('resize', function() {
        scrollHelper._documentHeight = -1;
    });

    $(window).on('scroll', _.bind(scrollHelper._setScrollDirection, scrollHelper));

    return scrollHelper;
});
