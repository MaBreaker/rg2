/*global rg2:false */
/*global Image:false */
(function () {
  var canvas, ctx, map;
  canvas = $("#rg2-map-canvas")[0];
  ctx = canvas.getContext('2d');
  map = new Image();
  ctx.displayAngle = 0;

  function loadNewMap(mapFile) {
    $("#rg2-map-load-progress-label").text(rg2.t("Loading map"));
    $("#rg2-map-load-progress").show();
    map.src = mapFile;
  }

  function drawSelectEventText() {
    if (!rg2.config.managing) {
      ctx.font = '30pt Arial';
      ctx.textAlign = 'center';
      ctx.fillStyle = rg2.config.BLACK;
      //MaB text alignment 
      //ctx.fillText(rg2.t("Select an event"), rg2.canvas.width / 2, rg2.canvas.height / 2);
      ctx.fillText(rg2.t("Select an event"), 0, 0);
    }
  }

  /* called whenever anything changes enough to need screen redraw
   * @param fromTimer {Boolean} true if called from timer: used to determine if animation time should be incremented
   */
  function redraw(fromTimer) {
    // Clear the entire canvas
    // first save current transformed state
    ctx.save();
    // reset everything back to initial size/state/orientation
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    // fill canvas to erase things: clearRect doesn't work on Android (?) and leaves the old map as background when changing
    ctx.globalAlpha = rg2.config.FULL_INTENSITY;
    ctx.fillStyle = rg2.config.GREY;
    ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    // go back to where we started
    ctx.restore();
    if (map.height > 0) {
      // set map background white so that dimmed maps do not have grey showing through
      ctx.fillStyle = rg2.config.WHITE;
      // this might have been reset by the ctx.restore()
      ctx.globalAlpha = rg2.config.FULL_INTENSITY;
      ctx.fillRect(0, 0, map.width, map.height);
      // set transparency of map
      ctx.globalAlpha = rg2.options.mapIntensity;
      // using non-zero map height to show we have a map loaded
      ctx.drawImage(map, 0, 0);
      var active = $("#rg2-info-panel").tabs("option", "active");
      if (active === rg2.config.TAB_DRAW) {
        rg2.courses.drawCourses(rg2.config.DIM);
        rg2.controls.drawControls(false);
        rg2.results.drawTracks();
        rg2.drawing.drawNewTrack();
      } else {
        if (active === rg2.config.TAB_CREATE) {
          rg2.manager.drawControls();
        } else {
          rg2.courses.drawCourses(rg2.config.DIM);
          rg2.results.drawTracks();
          rg2.controls.drawControls(false);
          //MaB pass timer
          rg2.animation.incrementAnimationTime(fromTimer);
          rg2.animation.drawAnimation();
        }
      }
    } else {
      drawSelectEventText();
    }
  }

  function applyMapRotation(angle, x, y, moveMap) {
    var pt, panelwidth, cx, cy;
    // save new absolute angle
    ctx.displayAngle = (ctx.displayAngle - angle) % (Math.PI * 2);
    // rotate around given co-ordinates
    ctx.translate(x, y);
    ctx.rotate(angle);
    if (moveMap) {
      //MaB mobile screens touch x, y
      if (rg2.input.infoPanelMaximised && window.innerWidth >= rg2.config.SMALL_SCREEN_BREAK_POINT) {
        panelwidth = $("#rg2-info-panel").outerWidth();
      } else {
        panelwidth = 0;
      }
      cx = panelwidth + (canvas.width - panelwidth) / 2;
      cy = (canvas.height * 0.9);
      // move map so that given point is centre-bottom of screen
      pt = ctx.transformedPoint(cx, cy);
      ctx.translate(pt.x - x, pt.y - y);
      rg2.input.lastX = cx;
      rg2.input.lastY = cy;
    } else {
      // put map back where it started
      ctx.translate(-1 * x, -1 * y);
    }
    ctx.save();
    redraw(false);
  }

  function rotateMap(direction) {
    // rotate a little bit from UI control input
    // direction is -1 for left and 1 for right
    var angle, pt;
    angle = direction * (Math.PI / 36);
    // rotate around centre of map
    //MaB rotation point
    //applyMapRotation(angle, (map.width / 2), (map.height / 2), false);
    // rotate around the last x, y
    pt = ctx.transformedPoint(rg2.input.lastX, rg2.input.lastY);
    applyMapRotation(angle, pt.x, pt.y, false);
  }

  function alignMap(angle, x, y) {
    // align to an absolute angle: 0 is up/north
    // rotate around defined x, y
    applyMapRotation((ctx.displayAngle - angle) % (Math.PI * 2), x, y, true);
  }

  function resetMapState() {
    // place map in centre of canvas and scale it down to fit
    var mapscale, heightscale, widthscale, panelwidth;
    heightscale = canvas.height / map.height;
    //MaB mobile screens
    if (rg2.input.infoPanelMaximised && window.innerWidth >= rg2.config.SMALL_SCREEN_BREAK_POINT) {
      panelwidth = $("#rg2-info-panel").outerWidth();
    } else {
      panelwidth = 0;
    }
    widthscale = (canvas.width - panelwidth) / map.width;
    rg2.input.lastX = panelwidth + (canvas.width - panelwidth) / 2;
    rg2.input.lastY = canvas.height / 2;
    rg2.input.zoomSize = 1;
    rg2.input.dragStart = null;
    // looks odd but this works for initialisation
    rg2.input.dragged = true;
    // don't stretch map: just shrink to fit
    if (heightscale < 1 && heightscale < widthscale) {
      mapscale = heightscale;
    } else if (widthscale < 1 && widthscale < heightscale) {
      mapscale = widthscale;
    } else {
      mapscale = 1;
    }
    // move map into view on small screens
    // avoid annoying jumps on larger screens
    /* if (rg2.input.infoPanelMaximised || window.innerWidth >= rg2.config.BIG_SCREEN_BREAK_POINT) {
      ctx.setTransform(mapscale, 0, 0, mapscale, $("#rg2-info-panel").outerWidth(), 0);
    } else {
      ctx.setTransform(mapscale, 0, 0, mapscale, 0, 0);
    } */
    ctx.setTransform(mapscale, 0, 0, mapscale, panelwidth * 0.5 + canvas.width * 0.5 - mapscale * map.width * 0.5, canvas.height * 0.5 - mapscale * map.height * 0.5);
    // don't need to rotate here since the call to setTransform above does that for us
    ctx.displayAngle = 0;
    ctx.save();
    redraw(false);
  }

  function showInfoDisplay(show, title, position) {
    var chevronRemove, chevronAdd;
    rg2.input.infoPanelMaximised = show;
    $("#rg2-resize-info").prop("title", rg2.t(title));
    //MaB remove static values to use floats
    $("#rg2-hide-info-panel-control").css("left", position);
    if (show) {
      $("#rg2-info-panel").show();
      chevronRemove = "fa-chevron-right";
      chevronAdd = "fa-chevron-left";
    } else {
      $("#rg2-info-panel").hide();
      chevronRemove = "fa-chevron-left";
      chevronAdd = "fa-chevron-right";
    }
    $("#rg2-hide-info-panel-icon").removeClass(chevronRemove).addClass(chevronAdd).prop("title", rg2.t(title));
  }

  function resizeInfoDisplay() {
    if (rg2.input.infoPanelMaximised) {
      showInfoDisplay(false, "Show info panel", "0px");
    } else {
      showInfoDisplay(true, "Hide info panel", "360px");
    }
    // move map around if necesssary
    //resetMapState();
  }

  function zoom(zoomDirection) {
    var pt, factor, tempZoom;
    factor = Math.pow(rg2.input.scaleFactor, zoomDirection);
    tempZoom = rg2.input.zoomSize * factor;
    // limit zoom to avoid things disappearing
    // chosen values seem reasonable after some quick tests
    if ((tempZoom < 50) && (tempZoom > 0.05)) {
      rg2.input.zoomSize = tempZoom;
      pt = ctx.transformedPoint(rg2.input.lastX, rg2.input.lastY);
      ctx.translate(pt.x, pt.y);
      ctx.scale(factor, factor);
      ctx.translate(-pt.x, -pt.y);
      ctx.save();
      redraw(false);
    }
  }

  function trackTransforms(ctx) {
    var xform, svg, savedTransforms, save, restore, scale, translate, setTransform, pt, rotate;
    svg = document.createElementNS("http://www.w3.org/2000/svg", 'svg');
    xform = svg.createSVGMatrix();
    savedTransforms = [];
    save = ctx.save;
    ctx.save = function () {
      savedTransforms.push(xform.translate(0, 0));
      return save.call(ctx);
    };
    restore = ctx.restore;
    ctx.restore = function () {
      xform = savedTransforms.pop();
      return restore.call(ctx);
    };
    scale = ctx.scale;
    ctx.scale = function (sx, sy) {
      xform = xform.scaleNonUniform(sx, sy);
      return scale.call(ctx, sx, sy);
    };
    translate = ctx.translate;
    ctx.translate = function (dx, dy) {
      xform = xform.translate(dx, dy);
      return translate.call(ctx, dx, dy);
    };
    setTransform = ctx.setTransform;
    ctx.setTransform = function (a, b, c, d, e, f) {
      xform.a = a;
      xform.b = b;
      xform.c = c;
      xform.d = d;
      xform.e = e;
      xform.f = f;
      return setTransform.call(ctx, a, b, c, d, e, f);
    };
    pt = svg.createSVGPoint();
    ctx.transformedPoint = function (x, y) {
      // converts x, y screen co-ords to x, y in map image
      pt.x = x;
      pt.y = y;
      return pt.matrixTransform(xform.inverse());
    };
    //ctx.getTransform = function () {
    //  return xform;
    //};
    //transform = ctx.transform;
    //ctx.transform = function (a, b, c, d, e, f) {
    //  m2 = svg.createSVGMatrix();
    //  m2.a = a;
    //  m2.b = b;
    //  m2.c = c;
    //  m2.d = d;
    //  m2.e = e;
    //  m2.f = f;
    //  xform = xform.multiply(m2);
    //  return transform.call(ctx, a, b, c, d, e, f);
    //};
    rotate = ctx.rotate;
    ctx.rotate = function (radians) {
      xform = xform.rotate(radians * 180 / Math.PI);
      return rotate.call(ctx, radians);
    };
  }

  function getMapSize() {
    return { height: map.height, width: map.width };
  }

  function resizeCanvas() {
    rg2.input.scaleFactor = rg2.config.DEFAULT_SCALE_FACTOR;

    //MaB calculate title bar for mobile screens
    //TODO use flexbox and get a rid of these
    var y_pos, y_box;
    y_pos = $("#rg2-header-container").height() + 4;
    y_box = ($("#rg2-info-panel").css("box-sizing") === "border-box") ? 0 : 8;
    // allow for header
    $("#rg2-container").css("height", window.innerHeight - y_pos);
    $("#rg2-info-panel").css("height", window.innerHeight - y_pos - y_box); // content-box -8

    $("#rg2-info-panel-tab-body").css("height", window.innerHeight - y_pos - y_box - 8 - 38);

    canvas.width = window.innerWidth;
    // allow for header
    //TODO fix this 5px gap at bottom 
    canvas.height = window.innerHeight - y_pos;

    rg2.ui.setTitleBar();
    resetMapState();
  }

  function mapLoadedCallback() {
    $("#rg2-map-load-progress").hide();
    resetMapState();
    if (rg2.config.managing) {
      rg2.manager.mapLoadCallback();
    }
  }

  function addListeners() {
    canvas.addEventListener('touchstart', rg2.handleTouchStart, false);
    canvas.addEventListener('touchmove', rg2.handleTouchMove, false);
    canvas.addEventListener('touchend', rg2.handleTouchEnd, false);
    canvas.addEventListener('DOMMouseScroll', rg2.handleScroll, false);
    canvas.addEventListener('mousewheel', rg2.handleScroll, false);
    canvas.addEventListener('mousedown', rg2.handleMouseDown, false);
    canvas.addEventListener('mousemove', rg2.handleMouseMove, false);
    canvas.addEventListener('mouseup', rg2.handleMouseUp, false);
    window.addEventListener('resize', resizeCanvas, false);    // force redraw once map has loaded
    map.addEventListener("load", function () {
      mapLoadedCallback();
    }, false);
  }

  function setUpCanvas() {
    addListeners();
    trackTransforms(ctx);
    resizeCanvas();
  }
  rg2.zoom = zoom;
  rg2.rotateMap = rotateMap;
  rg2.alignMap = alignMap;
  rg2.redraw = redraw;
  rg2.canvas = canvas;
  rg2.setUpCanvas = setUpCanvas;
  rg2.ctx = ctx;
  rg2.addListeners = addListeners;
  rg2.resetMapState = resetMapState;
  rg2.getMapSize = getMapSize;
  rg2.loadNewMap = loadNewMap;
  rg2.resizeInfoDisplay = resizeInfoDisplay;
}());
