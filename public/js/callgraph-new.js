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

Xhgui.callgraph = function(container, data, options) {
    // Create a new directed graph
    var g = new dagreD3.graphlib.Graph().setGraph({
        nodeSep: 1,
        rankSep: 50
    });

    var colors = d3.scaleLinear([0, 100], ['#fff', '#b63c71']);
    var textSize = d3.scaleLinear([0, 100], [0.5, 3]);
    
    var nodeStyle = function(node) {
        var ratio = node.value / data.total * 100;
        return 'fill: ' + colors(ratio) + ';'
    };

    var nodeLabelStyle = function(node) {
        var ratio = node.value / data.total * 100;
        return 'font-size: ' + textSize(ratio) + 'em;'
    };
    
    var nodeData = function(node) {
        var ratio = node.value / data.total * 100;
        return {
            metric: data.metric,
            value: node.value,
            ratio: ratio,
            callCount: node.callCount,
            name: node.name
        };
    };


    for (var i = 0, len = data.nodes.length; i < len; i++) {
        var node = data.nodes[i];
        g.setNode(node.name, {
            label: node.name + ' - ' + data.metric/* + ' ' + Xhgui.formatNumber(node.value)*/,
            style: nodeStyle(node),
            labelStyle: nodeLabelStyle(node),
            data: nodeData(node),
            description: node.name  + "\n" + "test",
        });
    }
    for (i = 0, len = data.links.length; i < len; i++) {
        var edge = data.links[i];
        var word = edge.callCount === 1 ? ' call' : ' calls';
        g.setEdge(
            edge.source,
            edge.target,
            {
                label: edge.callCount + word,
                curve: d3.curveBasis,
                arrowhead: "vee",
                arrowheadStyle: "fill: #696969;"
            }
        );
    }

    var el = d3.select("svg"),
        width = parseInt(el.style('width'), 10),
        height = 1000;

    var svg = d3.select("svg").attr('width', width).attr('height', height),
        inner = svg.select("g");

    // Set up zoom support
    var zoom = d3.zoom().on("zoom", function() {
        inner.attr("transform", d3.event.transform);
    });
    svg.call(zoom);

    // Create the renderer
    var render = new dagreD3.render();

    // Run the renderer. This is what draws the final graph.
    render(inner, g);

    // Center the graph
    var initialScale = 0.75;
    svg.call(zoom.transform, d3.zoomIdentity.translate((width - g.graph().width * initialScale) / 2, 20).scale(initialScale));

    svg.attr('height', g.graph().height * initialScale + 40);

    var hideTooltip = function(e) {
        $('.popover').hide();
        return true;
    };

    // Hide tooltip on zoom
    zoom.on('zoom.tooltip', hideTooltip);

    var nodes = svg.selectAll('.node');

    // Set tooltips on boxes.
    Xhgui.tooltip(inner, {
        bindTo: nodes,
        positioner: function (d, i, tooltip) {
            // Use the box's offset to position the tooltip.
            var position = this.getBoundingClientRect();
            var height = parseInt(tooltip.frame.style('height'), 10);

            var pos = {
                // 20 is a fudge factor.
                x: position.left + (position.width / 2) - 20,

                // Because we are using getBoundingClientRect() which returns
                // data based on the viewport, we have
                // to reverse the offsetTop() and height/2 operations that
                // the tooltip will apply. We also need to account for window scroll
                // position.
                y: position.top + window.scrollY,
            };
            console.log(pos);
            pos = {x: 200, y: 200};
            return pos;
        },
        formatter: function (d, i) {
            var data = g.node(d).data;
            var units = 'µs';
            if (data.metric.indexOf('mu') !== -1) {
                units = 'bytes';
            }
            var ratio = Xhgui.formatNumber(data.ratio);
            var value = Xhgui.formatNumber(data.value);
            var metric = Xhgui.metricName(data.metric);
            var urlName = '&symbol=' + encodeURIComponent(d);

            var label = '<h5>' + d + '</h5>' +
                '<strong>' + metric + ':</strong> ' + ratio + '% ' +
                ' (' + value + ' <span class="units">' + units + '</span>) ' +
                '<br />' +
                '<strong>Call count:</strong> ' + data.callCount +
                '<br />' +
                ' <a href="' + options.baseUrl + urlName + '">View symbol</a> <br />';
            return label;
        }
    });

    // // Color scale

    //
    // var textSize = d3.scale.linear()
    //     .domain([0, 100])
    //     .range([0.5, 3]);
    //
    // // Generate the style props for a node and its label
    // var nodeStyle = function(node) {
    //     var ratio = node.value / data.total * 100;
    //     return 'fill: ' + colors(ratio) + ';'
    // };
    // var nodeLabelStyle = function(node) {
    //     var ratio = node.value / data.total * 100;
    //     return 'font-size: ' + textSize(ratio) + 'em;'
    // }
    // Get a data hash for a given node.
    // var nodeData = function(node) {
    //     var ratio = node.value / data.total * 100;
    //     return {
    //         metric: data.metric,
    //         value: node.value,
    //         ratio: ratio,
    //         callCount: node.callCount,
    //         name: node.name
    //     };
    // }
    //
    // var el = d3.select(container),
    //     width = parseInt(el.style('width'), 10),
    //     height = 1000;
    //
    // var svg = d3.select(container).append('svg')
    //     .attr('class', 'callgraph')
    //     .attr('width', width)
    //     .attr('height', height);
    // var inner = svg.select('g');
    //
    // var g = new dagreD3.graphlib.Graph().setGraph({});
    // for (var i = 0, len = data.nodes.length; i < len; i++) {
    //     var node = data.nodes[i];
    //     g.setNode(node.name, {
    //         label: node.name + ' - ' + data.metric/* + ' ' + Xhgui.formatNumber(node.value)*/,
    //         // style: nodeStyle(node),
    //         // labelStyle: nodeLabelStyle(node),
    //         data: nodeData(node)
    //     });
    // }
    // for (i = 0, len = data.links.length; i < len; i++) {
    //     var edge = data.links[i];
    //     var word = edge.callCount === 1 ? ' call' : ' calls';
    //     g.setEdge(
    //         edge.source,
    //         edge.target,
    //         {label: edge.callCount + word}
    //     );
    // }
    //
    //
    // var zoom = d3.zoom().on("zoom", function() {
    //     inner.attr("transform", d3.event.transform);
    // });
    // svg.call(zoom);
    //
    //
    //
    // // // Lay out the graph more tightly than the defaults.
    // // var layout = dagreD3.layout()
    // //       .nodeSep(30)
    // //       .rankSep(30)
    // //       .rankDir("TB");
    //
    // // Render the graph.
    // var renderer = new dagreD3.render();
    //     // .layout(layout);
    //
    // // var oldEdge = renderer.drawEdgePaths();
    // // renderer.drawEdgePaths(function(g, root) {
    // //     var node = oldEdge(g, root);
    // //     node.attr('data-value', function(d) {
    // //         return d;
    // //     });
    // //     return node;
    // // });
    // //
    // // var oldNode = renderer.drawNodes();
    // // renderer.drawNodes(function(g, root) {
    // //     var node = oldNode(g, root);
    // //     node.attr('data-value', function(d) {
    // //         return d.replace(/\\/g, '_');
    // //     });
    // //     return node;
    // // });
    //
    // // Capture zoom object so tooltips can be hidden
    // // var zoom;
    // // renderer.zoom(function(graph, svg) {
    // //     zoom = d3.behavior.zoom().on('zoom', function() {
    // //         svg.attr('transform', 'translate(' + d3.event.translate + ')scale(' + d3.event.scale + ')');
    // //     });
    // //     return zoom;
    // // });
    //
    // renderer(svg, g);
    //
    // var initialScale = 0.75;
    // svg.call(zoom.transform, d3.zoomIdentity.translate((svg.attr("width") - g.graph().width * initialScale) / 2, 20).scale(initialScale));
    //
    // svg.attr('height', g.graph().height * initialScale + 40);
    //
    //
    // var hideTooltip = function(e) {
    //     $('.popover').hide();
    //     return true;
    // };
    // // Hide tooltip on zoom
    // // zoom.on('zoom.tooltip', hideTooltip);
    //
    // // Bind click events for function calls
    // var nodes = svg.selectAll('.node');
    // nodes.on('click', function(d, edge) {
    //     nodes.classed('active', false);
    //     d3.select(this).classed('active', true);
    //     var params = {
    //         symbol: d,
    //         threshold: options.threshold,
    //         metric: options.metric
    //     };
    //     var xhr = $.get(options.shortUrl + '&' + $.param(params))
    //     xhr.done(function(response) {
    //         details.addClass('active')
    //             .find('.details-content').html(response);
    //         Xhgui.tableSort(details.find('.table-sort'));
    //     });
    //     highlightSubtree(d);
    // });
    //
    // // Set tooltips on boxes.
    // // Xhgui.tooltip(el, {
    // //     bindTo: nodes,
    // //     positioner: function (d, i, tooltip) {
    // //         // Use the box's offset to position the tooltip.
    // //         var position = this.getBoundingClientRect();
    // //         var height = parseInt(tooltip.frame.style('height'), 10);
    // //
    // //         var pos = {
    // //             // 20 is a fudge factor.
    // //             x: position.left + (position.width / 2) - 20,
    // //
    // //             // Because we are using getBoundingClientRect() which returns
    // //             // data based on the viewport, we have
    // //             // to reverse the offsetTop() and height/2 operations that
    // //             // the tooltip will apply. We also need to account for window scroll
    // //             // position.
    // //             y: position.top + window.scrollY - el.node().offsetTop - (height / 2),
    // //         };
    // //         return pos;
    // //     },
    // //     formatter: function (d, i) {
    // //         var data = g.node(d).data;
    // //         var units = 'µs';
    // //         if (data.metric.indexOf('mu') !== -1) {
    // //           units = 'bytes';
    // //         }
    // //         var ratio = Xhgui.formatNumber(data.ratio);
    // //         var value = Xhgui.formatNumber(data.value);
    // //         var metric = Xhgui.metricName(data.metric);
    // //         var urlName = '&symbol=' + encodeURIComponent(d);
    // //
    // //         var label = '<h5>' + d + '</h5>' +
    // //             '<strong>' + metric + ':</strong> ' + ratio + '% ' +
    // //             ' (' + value + ' <span class="units">' + units + '</span>) ' +
    // //             '<br />' +
    // //             '<strong>Call count:</strong> ' + data.callCount +
    // //             '<br />' +
    // //             ' <a href="' + options.baseUrl + urlName + '">View symbol</a> <br />';
    // //         return label;
    // //     }
    // // });
    //
    // // Collects and iterates the subtree of nodes/edges and highlights them.
    // var highlightSubtree = function(root) {
    //     // var subtree = [root];
    //     // var nodes = [root];
    //
    //     // Clear width.
    //     // svg.selectAll('g.edgePath path').style('stroke-width', 1);
    //
    //     // while (nodes.length > 0) {
    //     //     var node = nodes.shift();
    //     //     var childNodes = g.successors(node);
    //     //     if (childNodes.length == 0) {
    //     //         break;
    //     //     }
    //     //
    //     //     for (var i = 0, len = childNodes.length; i < len; i++) {
    //     //         var edges = g.outEdges(childNodes[i]);
    //     //         for (var n = 0; n < edges.length; n++) {
    //     //             svg.select('g.edgePath[data-value=' + edges[i] + '] path').style('stroke-width', 5);
    //             // }
    //         // }
    //         // Append into the 'queue' so we can collect *all the nodes*
    //         // nodes = nodes.concat(childNodes);
    //         //
    //         // Collect the entire subtree so we can find and highlight edges.
    //         // subtree = subtree.concat(childNodes);
    //     // }
    //
    //     // var edges = [];
    //     // // Find the outgoing edges for each node in the subtree.
    //     // for (i = 0, len = subtree.length; i < len; i++) {
    //     //     node = subtree[i];
    //     //     edges = edges.concat(g.outEdges(node));
    //     // }
    //     //
    //     // // Highlight each edge in the subtree.
    //     // for (i = 0, len = edges.length; i < len; i++) {
    //     //     svg.select('g.edgePath[data-value=' + edges[i] + '] path').style('stroke-width', 5);
    //     // }
    // };
    //
    //
    // // Collects and iterates the subtree of nodes/edges and highlights them.
    // var highlightSubtree2 = function(root) {
    //     var i, len;
    //     var subtree = [root];
    //     var nodes = [root];
    //     while (nodes.length > 0) {
    //         var node = nodes.shift();
    //         var childNodes = g.successors(node);
    //         if (childNodes.length == 0) {
    //             break;
    //         }
    //         // Append into the 'queue' so we can collect *all the nodes*
    //         nodes = nodes.concat(childNodes);
    //
    //         // Collect the entire subtree so we can find and highlight edges.
    //         subtree = subtree.concat(childNodes);
    //     }
    //
    //     var edges = [];
    //     // Find the outgoing edges for each node in the subtree.
    //     for (i = 0, len = subtree.length; i < len; i++) {
    //         node = subtree[i];
    //         edges = edges.concat(g.outEdges(node));
    //     }
    //
    //     // Clear width.
    //     svg.selectAll('g.edgePath path').style('stroke-width', 1);
    //
    //     // Highlight each edge in the subtree.
    //     for (i = 0, len = edges.length; i < len; i++) {
    //         svg.select('g.edgePath[data-value=' + edges[i] + '] path').style('stroke-width', 5);
    //     }
    // };
    //
    // // Approximately center an element in the canvas.
    // var centerElement = function(rect) {
    //     console.log("here");
    //     // var zoomEl = svg.select('.zoom');
    //     // var position = rect[0].getBoundingClientRect();
    //
    //     // Get the box center so we can center the center.
    //     // var scale = zoom.scale();
    //     // var offset = zoom.translate();
    //     // var translate = [
    //     //     offset[0] - position.left - (position.width / 2) + (window.innerWidth / 2),
    //     //     offset[1] - position.top - (position.height / 2) + (window.innerHeight / 2)
    //     // ];
    //
    //     // zoom.translate(translate);
    //     // zoomEl.transition()
    //     //     .duration(750)
    //     //     .attr('transform', 'translate(' + translate[0] + ',' + translate[1] + ')scale(' + scale + ')');
    // };
    //
    // // Setup details view.
    // var details = $(options.detailView);
    // details.find('.button-close').on('click', function() {
    //     details.removeClass('active');
    //     details.find('.details-content').empty();
    //     return false;
    // });
    //
    // // Child symbol links move graph around.
    // details.on('click', '.child-symbol a', function(e) {
    //     var symbol = $(this).attr('title').replace(/\\/g, '_');
    //     var rect = $('[data-value="' + symbol + '"]');
    //
    //     // Not in the DOM; cancel.
    //     if (!rect.length) {
    //         return false;
    //     }
    //     hideTooltip();
    //     // centerElement(rect);
    //
    //     // Simulate a click as d3 and jQuery handle events differently.
    //     var evt = document.createEvent("MouseEvents");
    //     evt.initMouseEvent("click", true, true, window, 0, 0, 0, 0, 0, false, false, false, false, 0, null);
    //     rect[0].dispatchEvent(evt);
    //     return false;
    // });
};
