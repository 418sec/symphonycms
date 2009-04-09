var Symphony;

(function($) {
	Symphony = {
		WEBSITE: $('script')[0].src.split('/symphony/')[0],
		Language: {
			UNTITLED:         "Untitled",
			CREATE_ITEM:      "Add item",
			REMOVE_ITEMS:     "Remove selected items",
			CONFIRM_SINGLE:   "Are you sure you want to {$action} {$name}?",
			CONFIRM_MANY:     "Are you sure you want to {$action} {$count} items?",
			CONFIRM_ABSTRACT: "Are you sure you want to {$action}?",
			SHOW_CONFIG:      "Configure page settings",
			HIDE_CONFIG:      "Hide page settings",
			REORDER_ERROR:    "Reordering was unsuccessful.",
			PASSWORD:         "Password",
			CHANGE_PASSWORD:  "Change Password",
			REMOVE_FILE:      "Remove File"
		},
		Message: {
			post: function(message, type) {
				$('#notice').remove();
				$('h1').before('<div id="notice" class="' + type + '">' + message + '</div>');
			},
			clear: function(type) {
				$('#notice.' + type).remove();
			}
		}
	};

	$(document.documentElement).addClass('active');

	// Sortable lists
	var movable = {
		move: function(e) {
			var t,
			    n,
			    y = e.pageY;

			if (y < movable.min) {
				t = movable.target.prev();
				do {
					n = t.prev();
					if (n.length === 0 || y >= (movable.min -= n.height())) {
						movable.target.insertBefore(t);
						break;
					}
					t = n;
				} while (true);
			} else if (y > movable.max) {
				t = movable.target.next();
				do {
					n = t.next();
					if (n.length === 0 || y <= (movable.max += n.height())) {
						movable.target.insertAfter(t);
						break;
					}
					t = n;
				} while (true);
			} else {
				return;
			}

			movable.update(movable.target);
			movable.delta++;

			movable.target.parent().children().each(function(i) {
				$(this).toggleClass('odd', i % 2 === 0);
			});
		},
		drop: function() {
			$(document).unbind('mousemove', movable.move);
			$(document).unbind('mouseup', movable.drop);

			movable.target.removeClass('movable');

			if (movable.delta) {
				movable.target.trigger($.Event('reorder'));
			}
		},
		update: function(target) {
			var a = target.height(),
			    b = target.offset().top;

			movable.target = target;
			movable.min    = Math.min(b, a + (target.prev().offset().top || -Infinity));
			movable.max    = Math.max(a + b, b + (target.next().height() ||  Infinity));
		}
	};

	$('.orderable tr, .subsection > ol > li').live('mousedown', function(e) {
		if (!/^(?:h4|td)$/i.test(e.target.nodeName)) {
			return true;
		}

		movable.update($(this).addClass('movable'));
		movable.delta = 0;

		$(document).mousemove(movable.move);
		$(document).mouseup(movable.drop);

		return false;
	});

	$('table.orderable').live('reorder', function() {
		var t = $(this).addClass('busy'),
		    o = {
				    type: 'POST',
				    url: Symphony.WEBSITE + '/symphony/ajax/reorder' + location.href.slice(Symphony.WEBSITE.length + 9),
				    data: $('input', this).map(function(i) {return this.name + '=' + i}).get().join('&'),
				    complete: function(x) {
					    if (x.status === 200) {
						    Symphony.Message.clear('reorder');
					    } else {
						    Symphony.Message.post(Symphony.Language.REORDER_ERROR, 'reorder error');
					    }
					    t.removeClass('busy');
				    }
			    };

		$.ajax(o);
	});

	$('td, .subsection h4').live('click', function(e) {
		if (movable.delta || !/^(?:td|h4)$/i.test(e.target.nodeName)) {
			return true;
		}

		var i = $(this.parentNode).toggleClass('selected');

		i.trigger($.Event(i.hasClass('selected') ? 'select' : 'deselect'));
		i.find('td input').each(function() {this.checked = !this.checked});

		return false;
	});

	// Suggestion lists
	$('.tags > li').live('click', function() {
		var u = $(this.parentNode),
		    i = u.prev().find('input')[0],
		    t = this.title || $(this).text();

		i.focus();

		if (u.hasClass('singular')) {
			i.value = t;
		} else {
			var m = new RegExp('(^|,\\s*)' + t + '(?:$|\\s*,)').exec(i.value);

			if (m) {
				if (typeof i.setSelectionRange === 'function') {
					i.setSelectionRange(m = m.index + m[1].length, m + t.length);
				}
			} else {
				i.value = i.value.replace(/((?:\S+\s*)+)$/, '$1, ') + t;
			}
		}
	});

	$(function() {
		// Ugly DOM maintenance
		$('table input').parents('table').addClass('selectable');

		if (/[?&]debug[&=][^#]*#line-\d+$/.test(location.href)) {
			$('ol a').eq(parseInt(/\d+$/.exec(location.href)[0], 10) - 1).addClass('active');
		}

		$('ul.tags > li, #nav').mousedown(function() {
			return false;
		});

		// Change user password
		$('#change-password').each(function() {
			var a = $(this),
				b = a.next('p');

			if (a.find('label[title]').length > 0) {
				return;
			}

			b.remove();
			a.replaceWith('<div class="label">' + Symphony.Language.PASSWORD + ' <span><button id="change-password" type="button">' + Symphony.Language.CHANGE_PASSWORD + '</button></span></div>');

			$('#change-password').click(function() {
				$(this.parentNode.parentNode).replaceWith(a.add(b));
				a.find('input')[0].focus();
				return false;
			});
		});

		// Page settings
		$('#configure').each(function() {
			var c = $(this),
			    h = c.height(),
			    s = location.href.indexOf('/pages/new') === -1,
			    a = $('<a class="configure button" accesskey="c"></a>').appendTo('h2');

			if (s) {
				c.css('height', 0);
				a.addClass('active');
			}

			a.click(function() {
				a.attr('title', Symphony.Language[(s = !s) ? 'HIDE_CONFIG' : 'SHOW_CONFIG']).toggleClass('active');
				c.animate({height: s ? h : 0});
				return false;
			}).click();
		});

		// Upload fields
		$('<em>' + Symphony.Language.REMOVE_FILE + '</em>').appendTo($('label.file a').parent()).click(function() {
			$(this.parentNode).html('<input name="' + $(this).siblings('input').attr('name') + '" type="file">');
			$(this).remove();
			return false;
		});

		// confirm() dialogs
		$('button.confirm').live('click', function() {
			var n = document.title.split(/[\u2013]\s*/g)[2],
				t = Symphony.Language[n ? 'CONFIRM_SINGLE' : 'CONFIRM_ABSTRACT'];

			return confirm(t.replace('{$action}', this.firstChild.data.toLowerCase()).replace('{$name}', n));
		});

		if ($('select[name=with-selected] option.confirm').length > 0) {
			$('form').submit(function() {
				var i = $('table input:checked').length,
				    t = Symphony.Language[i > 1 ? 'CONFIRM_MANY' : 'CONFIRM_SINGLE'],
				    s = document.getElementsByName('with-selected')[0],
				    o = $(s.options[s.selectedIndex]);

				t = t.replace('{$action}', o.text().toLowerCase())
				     .replace('{$name}'  , $('table input:checked').parents('tr').find('td').eq(0).text())
				     .replace('{$count}' , i);

				return i > 0 && !o.hasClass('confirm') || confirm(t);
			});
		}

		// XSLT utilities
		$('#utilities a').each(function() {
			var a = $(this.parentNode),
			    r = new RegExp('href=["\']?../utilities/' + $(this).text());

			$('textarea').blur(function() {
				a[r.test(this.value) ? 'addClass' : 'removeClass']('selected');
			});
		});

		$('textarea').blur();

		// Repeating sections
		$('div.subsection').each(function() {
			var m = this,
			    t = $('.template', this),
			    h = t.map(function() {return $(this).height()}).get();

			t.remove().css('height', 0);
			$(this).append('<div class="actions"><a>' + Symphony.Language.CREATE_ITEM + '</a><a class="inactive">' + Symphony.Language.REMOVE_ITEMS + '</a></div>');
			$('.actions > a', this).click(update);

			if (t.length > 1) {
				var s = $(document.createElement('select'));

				t.each(function() {
					s.append(new Option($('h4', this).text()));
				});

				$('.actions', this).prepend(s);
			}

			var r = $('.actions > a.inactive', this),
			    i = 0;

			$(this).bind('select'  , function() {r.toggleClass('inactive', ++i === 0)});
			$(this).bind('deselect', function() {r.toggleClass('inactive', --i === 0)});

			$('form').submit(function() {
				$('ol > li', m).each(function(i) {
					$('input,select,textarea', this).each(function() {
						this.name = this.name.replace(/\[\-?\d+(?=])/, '[' + i);
					});
				});
			});

			function update() {
				var a = $(this);

				if (a.hasClass('inactive')) {
					return true;
				}

				if (a.is(':last-child')) {
					$('li.selected', m).animate({height: 0}, function() {
						$(this).remove();
					});
					a.addClass('inactive');
				} else {
					var i = s ? s[0].selectedIndex : 0,
					    w = $('ol', m);

					$(t[i]).clone(true).appendTo(w).animate({height: h[i]}, function() {
						$('input,select,textarea', this).eq(0).focus();
					});
				}

				return false;
			}
		});

		// Data source switcheroo
		$('select.filtered > optgroup').each(function() {
			var s = this.parentNode,
			    z = $(this).siblings('option').length,
			    o = $(this).remove().find('option');

			$('#context').change(function() {
				if ($(this.options[this.selectedIndex]).text() === o.label) {
					s.options.length = z;
					o.clone(true).appendTo(s);
				}
			});
		});

		$('*.contextual').each(function() {
			var a = $(this);

			$('#context').change(function() {
				var o = $(this.options[this.selectedIndex]).parent('optgroup'),
				    c = this.value.replace(/\W+/g, '_'),
				    g = o.attr('label').replace(/\W+/g, '_');

				a[(a.hasClass(c) || a.hasClass(g)) ^ a.hasClass('inverse') ? 'removeClass' : 'addClass']('irrelevant');
			});
		});

		$('#context').change();
	});
})(jQuery.noConflict());