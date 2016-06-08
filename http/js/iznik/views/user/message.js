define([
    'jquery',
    'underscore',
    'backbone',
    'iznik/base',
    'iznik/views/infinite',
    'iznik/views/chat/chat'
], function($, _, Backbone, Iznik) {
    Iznik.Views.User.Message = Iznik.View.extend({
        className: "marginbotsm botspace",

        events: {
            'click .js-caret': 'carettoggle',
            'click .js-fop': 'fop'
        },

        expanded: false,

        caretshow: function() {
            if (!this.expanded) {
                this.$('.js-replycount').addClass('reallyHide');
                this.$('.js-unreadcountholder').addClass('reallyHide');
                this.$('.js-promised').addClass('reallyHide');
                this.$('.js-caretdown').show();
                this.$('.js-caretup').hide();
            } else {
                this.$('.js-replycount').removeClass('reallyHide');
                this.$('.js-unreadcountholder').removeClass('reallyHide');
                this.$('.js-promised').removeClass('reallyHide');
                this.$('.js-caretdown').hide();
                this.$('.js-caretup').show();
            }
        },

        expand: function() {
            this.$('.js-caretdown').click();
        },

        setReply: function(text) {
            var self = this;

            try {
                // Clear the local storage, so that we don't get stuck here.
                localStorage.removeItem('replyto');
                localStorage.removeItem('replytext');
            } catch (e) {}

            this.$('.js-replytext').val(text);

            // We might get called back twice because of the html, body selector (which we need for browser compatibility)
            // so make sure we only actually click send once.
            self.readyToSend = true;

            $('html, body').animate({
                    scrollTop: self.$('.js-replytext').offset().top
                },
                2000,
                function() {
                    if (self.readyToSend) {
                        // Now send it.
                        self.readyToSend = false;
                        self.$('.js-send').click();
                    }
                }
            );
        },

        carettoggle: function() {
            this.expanded = !this.expanded;
            if (this.expanded) {
                this.$('.js-snippet').slideUp();
            } else {
                this.$('.js-snippet').slideDown();
            }
            this.caretshow();
        },

        fop: function() {
            var v = new Iznik.Views.Modal();
            v.open('user_home_fop');
        },

        updateReplies: function() {
            if (this.replies.length == 0) {
                this.$('.js-noreplies').fadeIn('slow');
            } else {
                this.$('.js-noreplies').hide();
            }
        },

        updateUnread: function() {
            var self = this;
            var unread = 0;

            // We might or might not have the chats, depending on whether we're logged in at this point.
            if (Iznik.Session.hasOwnProperty('chats')) {
                Iznik.Session.chats.each(function(chat) {
                    var refmsgids = chat.get('refmsgids');
                    _.each(refmsgids, function(refmsgid) {
                        if (refmsgid == self.model.get('id')) {
                            var thisun = chat.get('unseen');
                            unread += thisun;

                            if (thisun > 0) {
                                // This chat might indicate a new replier we've not got listed.
                                // TODO Could make this perform better than doing a full fetch.
                                self.model.fetch().then(function() {
                                    self.replies.add(self.model.get('replies'));
                                    self.updateReplies();
                                });
                            }
                        }
                    });
                });
            }

            if (unread > 0) {
                this.$('.js-unreadcount').html(unread);
                this.$('.js-unreadcountholder').show();
            } else {
                this.$('.js-unreadcountholder').hide();
            }
        },

        watchChatRooms: function() {
            var self = this;

            if (this.inDOM() && Iznik.Session.hasOwnProperty('chats')) {
                // If the number of unread messages relating to this message changes, we want to flag it in the count.  So
                // look for chats which refer to this message.  Note that chats can refer to multiple.
                Iznik.Session.chats.fetch().then(function() {
                    Iznik.Session.chats.each(function (chat) {
                        self.listenTo(chat, 'change:unseen', self.updateUnread);
                    });

                    self.updateUnread();

                    self.listenToOnce(Iznik.Session.chats, 'newroom', self.watchChatRooms);
                });
            }
        },

        stripGumf: function(property) {
            var text = this.model.get(property);

            if (text) {
                // console.log("Strip photo", text);
                // Strip photo links - we should have those as attachments.
                text = text.replace(/You can see a photo[\s\S]*?jpg/, '');
                text = text.replace(/Check out the pictures[\s\S]*?https:\/\/trashnothing[\s\S]*?pics\/\d*/, '');
                text = text.replace(/You can see photos here[\s\S]*?jpg/m, '');
                text = text.replace(/https:\/\/direct.*jpg/m, '');

                // FOPs
                text = text.replace(/Fair Offer Policy applies \(see https:\/\/[^]*\)/, '');

                // Footers
                text = text.replace(/--[\s\S]*Get Freegling[\s\S]*book/m, '');

                text = text.trim();
                // console.log("Stripped photo", text);
            } else {
                text = '';
            }

            this.model.set(property, text);
        },

        render: function() {
            var self = this;

            var outcomes = self.model.get('outcomes');
            if (outcomes && outcomes.length > 0) {
                // Hide completed posts by default.
                self.$el.hide();
            }

            this.stripGumf('textbody');

            // The server will have returned us a snippet.  But if we've stripped out the gumf and we have something
            // short, use that instead.
            var tb = this.model.get('textbody');
            if (tb.length < 60) {
                this.model.set('snippet', tb);
            }

            var p = Iznik.View.prototype.render.call(self);
            p.then(function() {
                if (self.expanded) {
                    self.$('.panel-collapse').collapse('show');
                } else {
                    self.$('.panel-collapse').collapse('hide');
                }

                // There is an unpleasant problem here with our async render.  CollectionView can call render()
                // more than once, as it is entitled to do.  Because of the order in which the promise callbacks
                // get executed, we can clear out the groups (and also the atts below), and then process the callbacks,
                // which means we can render and append more than once - so we get duplicate group entries.  To avoid
                // this, we save off which ones we have rendered in this object, and check it before appending.
                var groups = self.model.get('groups');
                self.groupsRendered = [];
                self.$('.js-groups').empty();
                _.each(groups, function(group) {
                    var v = new Iznik.Views.User.Message.Group({
                        model: new Iznik.Model(group)
                    });
                    v.render().then(function(v) {
                        if (!self.groupsRendered[group.id]) {
                            self.groupsRendered[group.id] = true;
                            self.$('.js-groups').append(v.el);
                        }
                    });
                });

                self.attsRendered = [];
                self.$('.js-attlist').empty();
                _.each(self.model.get('attachments'), function (att) {
                    var mod = new Iznik.Model(att);
                    mod.set('subject', self.model.get('subject'));
                    var v = new Iznik.Views.User.Message.Photo({
                        model: mod
                    });
                    v.render().then(function(v) {
                        if (!self.attsRendered[att.id]) {
                            self.attsRendered[att.id] = true;
                            self.$('.js-attlist').append(v.el);
                        }
                    });
                });

                if (self.$('.js-replies').length > 0) {
                    // Show and update the reply details.
                    var replies = self.model.get('replies');
                    if (replies.length > 0) {
                        self.$('.js-noreplies').hide();
                        self.$('.js-replies').empty();
                        self.replies = new Iznik.Collection(replies);
                        self.listenTo(self.model, 'change:replies', self.updateReplies);
                        self.updateReplies();

                        self.repliesView = new Backbone.CollectionView({
                            el: self.$('.js-replies'),
                            modelView: Iznik.Views.User.Message.Reply,
                            modelViewOptions: {
                                collection: self.replies,
                                message: self.model,
                                offers: self.options.offers
                            },
                            collection: self.replies
                        });

                        self.repliesView.render();

                        // We might have been asked to open up one of these messages because we're showing the corresponding
                        // chat.
                        if (self.options.chatid ) {
                            var model = self.replies.get(self.options.chatid);
                            console.log("Get chat model", model);
                            if (model) {
                                var view = self.repliesView.viewManager.findByModel(model);
                                console.log("Got view", view, view.$('.js-caret'));
                                // Slightly hackily jump up to find the owning message and click to expand.
                                view.$el.closest('.panel-heading').find('.js-caret').click();
                            }
                            self.replies.each(function(reply) {
                                console.log("Compare", reply.get('chatid'), self.options.chatid);
                                if (reply.get('chatid') == self.options.chatid) {
                                    console.log("Found it");
                                }
                            });
                        }
                    } else {
                        self.$('.js-noreplies').show();
                    }
                }

                self.updateUnread();

                // We want to keep an eye on chat messages, because those which are in conversations referring to our
                // message should affect the counts we display.
                self.watchChatRooms();

                // If the number of promises changes, then we want to update what we display.
                self.listenTo(self.model, 'change:promisecount', self.render);

                // By adding this at the end we avoid border flicker.
                self.$el.addClass('panel panel-info');
            });

            return(p);
        }
    });

    Iznik.Views.User.Message.Group = Iznik.View.extend({
        template: "user_message_group",

        render: function() {
            var self = this;
            var p = Iznik.View.prototype.render.call(this);
            p.then(function(self) {
                self.$('.timeago').timeago();
            });
            return(p);
        }
    });

    Iznik.Views.User.Message.Photo = Iznik.View.extend({
        tagName: 'li',

        events: {
            'click': 'zoom'
        },
        
        template: 'user_message_photo',

        zoom: function (e) {
            e.preventDefault();
            e.stopPropagation();

            var v = new Iznik.Views.User.Message.PhotoZoom({
                model: this.model
            });
            v.render();
        }
    });

    Iznik.Views.User.Message.PhotoZoom = Iznik.Views.Modal.extend({
        template: 'user_message_photozoom'
    });

    Iznik.Views.User.Message.Reply = Iznik.View.extend({
        tagName: 'li',

        template: 'user_message_reply',

        events: {
            'click .js-chat': 'dm',
            'click .js-promise': 'promise',
            'click .js-renege': 'renege'
        },

        dm: function() {
            var self = this;
            require(['iznik/views/chat/chat'], function(ChatHolder) {
                console.log("Open chat to", self.model.get('user').id);
                ChatHolder().openChat(self.model.get('user').id);
            })
        },

        promise: function() {
            var self = this;

            var v = new Iznik.Views.User.Message.Promise({
                model: new Iznik.Model({
                    message: self.options.message.toJSON2(),
                    user: self.model.get('user')
                }),
                offers: self.options.offers
            });

            self.listenToOnce(v, 'promised', function() {
                self.options.message.fetch().then(function() {
                    self.render.call(self, self.options);
                })
            });

            v.render();
        },

        renege: function() {
            var self = this;

            var v = new Iznik.Views.Confirm({
                model: self.model
            });
            v.template = 'user_message_renege';

            self.listenToOnce(v, 'confirmed', function() {
                $.ajax({
                    url: API + 'message/' + self.options.message.get('id'),
                    type: 'POST',
                    data: {
                        action: 'Renege',
                        userid: self.model.get('user').id
                    }, success: function() {
                        self.options.message.fetch().then(function() {
                            self.render.call(self, self.options);
                        });
                    }
                })
            });

            v.render();
        },

        chatPromised: function() {
            var self = this;
            self.model.set('promised', true);
            self.render();
        },

        render: function() {
            var self = this;
            var p;

            var chat = Iznik.Session.chats.get({
                id: self.model.get('chatid')
            });

            // We might not find this chat if the user has closed it.
            if (!_.isUndefined(chat)) {
                // If the number of unseen messages in this chat changes, update this view so that the count is
                // displayed here.
                self.listenToOnce(chat, 'change:unseen', self.render);
                self.model.set('unseen', chat.get('unseen'));
                self.model.set('message', self.options.message.toJSON2());
                self.model.set('me', Iznik.Session.get('me'));
                p = Iznik.View.prototype.render.call(self).then(function() {
                    self.$('.timeago').timeago();
                });

                // We might promise to this person from a chat.
                self.listenTo(chat, 'promised', _.bind(self.chatPromised, self));
            } else {{
                p = new Promise(function(resolve, reject) {
                    resolve(self);
                });
            }}

            return(this);
        }
    });

    Iznik.Views.User.Message.Promise = Iznik.Views.Confirm.extend({
        template: 'user_message_promise',

        promised: function() {
            var self = this;

            $.ajax({
                url: API + 'message/' + self.model.get('message').id,
                type: 'POST',
                data: {
                    action: 'Promise',
                    userid: self.model.get('user').id
                }, success: function() {
                    self.trigger('promised')
                }
            })
        },

        render: function() {
            var self = this;
            this.listenToOnce(this, 'confirmed', this.promised);
            var p = this.open(this.template);
            p.then(function() {
                var msgid = self.model.get('message').id;

                self.options.offers.each(function(offer) {
                    self.$('.js-offers').append('<option value="' + offer.get('id') + '" />');
                    self.$('.js-offers option:last').html(offer.get('subject'));
                });

                self.$('.js-offers').val(msgid);

            });

            return(p);
        }
    });
});