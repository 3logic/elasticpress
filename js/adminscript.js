(function admin_init(ko) {
    var $ = window.jQuery;

    // Console-polyfill. MIT license.
    // https://github.com/paulmillr/console-polyfill
    // Make it safe to do console.log() always.
    (function(con) {
      'use strict';
      var prop, method;
      var empty = {};
      var dummy = function() {};
      var properties = 'memory'.split(',');
      var methods = ('assert,clear,count,debug,dir,dirxml,error,exception,group,' +
         'groupCollapsed,groupEnd,info,log,markTimeline,profile,profiles,profileEnd,' +
         'show,table,time,timeEnd,timeline,timelineEnd,timeStamp,trace,warn').split(',');
      while (prop = properties.pop()) con[prop] = con[prop] || empty;
      while (method = methods.pop()) con[method] = con[method] || dummy;
    })(this.console = this.console || {}); // Using `this` for web workers.
    // ----------------------------------------------------------------------


    var noop = function(){};

    // var Site = function(site_descriptor){
    //     for(var i in site_descriptor){
    //         this[i] = site_descriptor[i];
    //     }

    //     this.outdated =  Date.parse(this.date_reference) < Date.parse(appData.current.modification_date);
    //     this.edit_link = ko.observable(site_descriptor.edit_link);

    //     this.has_edit_link = ko.pureComputed(function(){
    //         return this.edit_link() !== null;
    //     },this);

    //     this.checked = ko.observable(this.has_edit_link());
        
        
    //     this.will_clone = ko.pureComputed(function(){
    //         return this.has_edit_link() || this.checked();
    //     },this);
        
    // };

    var STATUSES = { 'running':'_running_', 'ok':'_ok_', 'ko':'_ko_' };

    var SettingsApp = function(appData){
        this.STATUSES = STATUSES;

        this.mapping_version = ko.observable(window.elasticpress_admin.mapping_version);

        this.status_document_count = ko.observable(null);
        this.status_history_count = ko.observable(null);
        this.status_preset_count = ko.observable(null);
        this.server_test_status = ko.observable(null);
        this.mapping_test_status = ko.observable(null);
        
        this.checked_clean_extra = ko.observable(false);

        this.server_test_permitted = ko.pureComputed(function(){
            return this.server_test_status() !== STATUSES.running;
        },this);

        /**
         * True se c'è una richiesta ajax in corso
         * @type {Boolean}
         */
        this.is_requesting = ko.observable(false);

        this.reindex_result = ko.observable(null);
        this.index_all_posts_result = ko.observable(null);
        this.clean_all_result = ko.observable(null);
        

        // this.is_cloning.subscribe(function(cloning){
        //  $('#publish').prop('disabled', cloning);
        // });
        this.do_server_test();
        this.do_mapping_test();
        this.do_retrieve_status();
        //this.check_previous_shell_status();
    };

    SettingsApp.OPERATIONS = ['test', 'reindex', 'index_all_posts', 'clean_all', 'status'];

    SettingsApp.prototype = {

        do_server_test: function(){
            var value = $('#plugin_servers').val();
            var pieces = value.split(','), lp, servers = [];
            for (var pk in pieces){
                lp = (pieces[pk].trim().split(':'));
                if(lp.length ==2){
                    servers.push({'host':lp[0].trim(),'port':lp[1].trim()});
                }
            }
            this.server_test_status( STATUSES.running );
            this._do_ajax('test', {'servers':servers, 'tests':['connection']} , function(err,data){
                if(data && data.status=='ok')
                    this.server_test_status( STATUSES.ok );
                else
                    this.server_test_status( STATUSES.ko );
            }.bind(this))
        },

        do_mapping_test: function(){
            this.mapping_test_status( STATUSES.running );
            this._do_ajax('test', {'tests':['mapping']} , function(err,data){
                if(data && data.status=='ok')
                    this.mapping_test_status( STATUSES.ok );
                else
                    this.mapping_test_status( STATUSES.ko );
            }.bind(this))
        },

        do_retrieve_status: function(){
            this._do_ajax('status', {'api_key': window.elasticpress_admin.api_key} , function(err,data){
                if(data && data.success){
                    this.status_document_count(data.status.document_count);
                    this.status_history_count(data.status.history_count);
                    this.status_preset_count(data.status.preset_count);
                }
            }.bind(this))
        },

        reindex : function(){
            this._do_ajax('reindex', {'api_key': window.elasticpress_admin.api_key} , function(err,data){
                if(err || !data){
                    this.reindex_result(false);
                }else{
                    this.reindex_result(data.success ? true : false);
                }
            }.bind(this));
        },

        index_all_posts: function(){
            this._do_ajax('index_all_posts', {'api_key': window.elasticpress_admin.api_key} , function(err,data){
                if(err || !data){
                    this.index_all_posts_result(false);
                }else{
                    this.index_all_posts_result(data.success ? true : false);
                }
            }.bind(this));
        },

        clean_all: function(){
            this._do_ajax('clean_all', {'api_key': window.elasticpress_admin.api_key, 'clean_extra': this.checked_clean_extra()} , function(err,data){
                if(err || !data){
                    this.clean_all_result(false);
                }else{
                    this.clean_all_result(data.success ? true : false);
                }
            }.bind(this));
        },

        /**
         * Restituisce l'url dell'operazione specificata
         * @param  {string} operation - Operazione @see SettingsApp~OPERATIONS
         * @return {string|boolean} L'url o false
         */
        _get_operation_url : function(operation){
            if(SettingsApp.OPERATIONS.indexOf(operation) < 0){
                return false;
            }
            return appData.ajaxUrl + (operation.toLowerCase());
        },

        /**
         * Esegue un'operazione
         * @param  {string}   operation L'operazione da eseguire
         * @param  {object}   params    Parametri da passare all'operazione
         * @param  {Function} callback  Chiamata quando l'operazione è terminata con err,data
         */
        _do_ajax : function(operation, params, callback){
            var url = this._get_operation_url(operation);
            this.is_requesting(true);
            var ajax_params = {
                    url: url,
                    type: 'POST',
                    data: params,
                    success: function(data){
                        callback(null, data);
                    },
                    error: function(err){
                        callback(err);
                    },
                    complete : function(){
                        this.is_requesting(false);
                    }.bind(this)
                };
            
            $.ajax(ajax_params);
        },

        /**
         * Controlla se ci sono shell attive lanciate in passato
         */
        check_previous_shell_status : function(){
            return;
            var check = function(){
                var params = {
                    blog_id: this.blog_id,
                    post_id: this.post_id
                },
                retry = 10;
                this._do_ajax('status', params, function(err, response){
                    if(err){
                        console.error('error nel check status',err);
                        return setTimeout(check.bind(this), 3000);
                    }
                    if(response.running === true){
                        this.is_cloning(true);
                        setTimeout(check.bind(this), 3000);
                    }else{
                        this._update_sites_infos(function(err){
                            if(err){
                                console.error('error updating site info',err);
                            }
                            this.is_cloning(false);
                        }.bind(this));
                    }
                    
                }.bind(this));
            };
            check.call(this);           
        }
    };
    
    $(document).ready(function(){       
        appData = {ajaxUrl:$('#ep_settings_form').data('ajaxUrl')};
        ko.applyBindings(new SettingsApp(appData), document.getElementById('ep_settings_wrapper'));
    });

})(ko);