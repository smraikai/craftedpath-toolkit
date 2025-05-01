/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/dom-ready":
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
/***/ (function(module) {

module.exports = window["wp"]["domReady"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
!function() {
/*!*****************************************!*\
  !*** ./assets/js/admin-enhancements.js ***!
  \*****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__);

_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default()(() => {
  const adminSidebar = document.querySelector('#adminmenumain');
  const collapseButton = document.querySelector('#collapse-button');
  const isRTL = document.body.classList.contains('rtl');
  const initialWidth = localStorage.getItem('admin-redesign-sidebar-width') || 300;
  if (initialWidth < 175) {
    document.body.classList.add('folded');
  }
  function resize(e) {
    const width = isRTL ? window.innerWidth - e.clientX : e.clientX;
    document.body.classList.toggle('folded', width < 120);
    let newWidth = width;

    // in order to make the switch between the folded and unfolded sidebar smoother
    // we need to set the width to a fixed value when the sidebar is in the transition zone
    // this is the zone between 120px and 175px
    if (width < 120) {
      newWidth = 70;
    }
    if (width < 175 && width >= 120) {
      newWidth = 175;
    }

    // add a snap point at 300px
    if (width > 290 && width < 310) {
      newWidth = 300;
    }

    // add a max width of 500px to the sidebar
    if (newWidth > 500) {
      newWidth = 500;
    }

    // store the new width in local storage
    localStorage.setItem('admin-redesign-sidebar-width', newWidth);
    document.documentElement.style.setProperty('--admin-redesign-sidebar-width', `${newWidth}px`);
  }
  if (adminSidebar) {
    const resizeHandle = document.createElement('div');
    resizeHandle.id = 'admin-sidebar-resize-handle';
    adminSidebar.appendChild(resizeHandle);
    resizeHandle.addEventListener('mousedown', function (e) {
      e.preventDefault();
      resizeHandle.classList.add('active');
      document.addEventListener('mousemove', resize);
      document.addEventListener('mouseup', () => {
        resizeHandle.classList.remove('active');
        document.removeEventListener('mousemove', resize);
      });
    });
  }
  collapseButton.addEventListener('click', () => {
    const isFolded = document.body.classList.contains('folded');
    const newFolded = !isFolded;
    localStorage.setItem('admin-redesign-sidebar-width', newFolded ? 70 : 300);
    document.documentElement.style.setProperty('--admin-redesign-sidebar-width', newFolded ? '70px' : '300px');
  });
});
}();
/******/ })()
;
//# sourceMappingURL=admin-enhancements.js.map