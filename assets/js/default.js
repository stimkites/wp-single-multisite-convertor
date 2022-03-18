var __wtssms = (function($){

    /**
     * Fetch compatible to all browsers
     *
     * arguments: action, data, success, complete
     */
    var fetch = function(){
            $('#save_result').html('');
            $('.icon').hide();
            block_form( arguments[0] === 'launch' );
            $.ajax({
                url     : 	ajaxurl,
                data:	{
                    action  :   wtssms.action,
                    nonce   :   wtssms.nonce,
                    do      :   arguments[0],
                    data    :   arguments[1]
                },
                type    : 'post',
                dataType: 'json',
                timeout : 0,
                success : arguments[2] ? arguments[2] : null,
                complete: arguments[3] ? arguments[3] : unblock_form,
                error   : arguments[4] ? arguments[4] : function( a, b, error ){ if( error ) alert( error ); console.log( error ); }
            });
        },

        /**
         * Block main form for request action
         */
        block_form = function ( action ) {
            $( 'body' ).prepend(
                '<div id="wt_ss_ms_main_block"'
                + ( action ? '' : 'class="heartbeat"' )
                + '></div>' +
                ( action
                    ?   '<div id="wt_ss_ms_action" class="heartbeat">' +
                            '<div class="current">' + wtssms.init_msg + '</div>' +
                            '<div class="progress-bar">' +
                                '<div class="bar"></div>' +
                            '</div>' +
                            '<div class="log"></div>' +
                        '</div>'
                    : ''
                )

            );
            $('#wt_ss_ms_main_block').fadeIn( 200 );
            if( action )
                $('#wt_ss_ms_action').fadeIn( 100 );
        },

        /**
         * Block main form for request action
         */
        unblock_form = function () {
            if( $('#wt_ss_ms_action').length )
                $('#wt_ss_ms_action').fadeOut( 100 );
            $('#wt_ss_ms_main_block').fadeOut( 200, function(){ $('#wt_ss_ms_main_block').remove() } );
        },

        /**
         * Check what is going on
         */
        check_action = function(){
            wtssms.no_cache = Number( wtssms.no_cache ) + 1;
            var url = wtssms.check_url + '?v=' + wtssms.no_cache;
            $.get( url, function( data ){
                data = JSON.parse( data );
                if( data.error )
                    return $('#wt_ss_ms_action .current').html( data.error );
                $('#wt_ss_ms_action .current').html( data.current );
                $('#wt_ss_ms_action .log').html( data.log.join("<br/>") );
                $('#wt_ss_ms_action .bar').css( 'width', data.step / data.total * 100 + '%' );
            } );
        },

        /**
         * No propagation
         *
         * @param e
         * @returns {boolean}
         */
        noclick = function( e ){
            if(!e) return false;
            e.stopPropagation();
            e.preventDefault();
            return false;
        },

        /**
         * Add domain row
         *
         * @param e
         * @returns {boolean}
         */
        add_domain = function( e ){
            var t = $(this).parents('table').first();
            t.find('tbody').append( '<tr>' + t.find('.nd-tpl').html().replace( 'disabled', '' ) + '</tr>' );
            assign_domain_buts();
            return noclick( e );
        },

        /**
         * Add first domain on page load
         */
        add_first_domain = function(){
            if( 0 === $('input[name=domains\\[\\]]:not(:disabled)').length ) $('.add-domain').trigger( 'click' );
        },

        /**
         * Delete domain row
         *
         * @returns {boolean}
         */
        delete_domain = function( e ){
            $(this).parents('tr').first().remove();
            return noclick( e );
        },

        /**
         * Tab switch
         */
        assign_tabs = function(){
            $('.wt-ssms-wrap .nav-tab:not(.disabled)').off().click(function(){
                $(this)
                    .addClass('nav-tab-active')
                    .siblings()
                    .removeClass('nav-tab-active');
                $('#wttab-' + $(this).data('tab'))
                    .addClass('active')
                    .siblings()
                    .removeClass('active');
            });
            $('.wt-ssms-wrap input[name=complete_copy]').off().on( 'change', function(){
                if( '1' === $(this).val() )
                    $('.wt-ssms-wrap .complete-copy-option').show();
                else
                    $('.wt-ssms-wrap .complete-copy-option').hide();
            });
            $('.wt-ssms-wrap input[name=complete_copy]:checked').trigger( 'change' );
        },

        /**
         * Reassign domain buttons
         */
        assign_domain_buts = function(){
            $('.add-domain').off().click( add_domain );
            $('.delete-domain').off().click( delete_domain );
        },

        /**
         * Reassign main events
         */
        assign_main_events = function(){
            $('#save_only').click( save_options );
            $('#save_n_go').click( launch_operation );
        },

        assign_domain_select = function(){
            $('select[name=cp_primary_domain]').change(function(){
                $('.copy-domain').html( $('select[name=cp_primary_domain] option:selected').html() );
            }).trigger( 'change' );
        },

        check_interval = 0,

        /**
         * Launch pending operation
         *
         * @param e
         * @returns {*}
         */
        launch_operation = function( e ){
            if( !$(this).hasClass( 'accept' ) ) {
                return save_options( e, warn_user );
            }
            hide_warning();
            check_interval = setInterval( check_action, 600 );
            fetch( 'launch', null, after_launch, launch_complete );
            return noclick( e );
        },

        /**
         * Render extra things on launch is complete
         */
        launch_complete = function(){
            clearInterval( check_interval );
            $('#wt_ss_ms_action')
                .removeClass('heartbeat')
                .prepend('<button class="wt-close-button" id="close_action_hover"></button>')
                .append( wtssms.log_url );
            $('#close_action_hover').click( unblock_form );
        },

        /**
         * Redirect after launch
         *
         * @param data
         */
        after_launch = function( data ){
            if( ! data.error )
                return window.location = data.result;
            console.log( data.error );
        },

        /**
         * Warn user before launch
         *
         * @param data
         */
        warn_user = function( data ){
            if( data.error ) {
                console.log( data.error );
                $('#save_result').html( data.error );
                $('.icon-cross').show();
            }else{
                $('#save_n_go').addClass( 'accept' ).html( $('#save_n_go').data('go') );
                $('#warning').slideDown();
                setTimeout( hide_warning, 30000 );
            }
        },

        /**
         * Hide warning
         */
        hide_warning = function(){
            $('#save_n_go').removeClass( 'accept' ).html( $('#save_n_go').data('nogo') );
            $('#warning').slideUp();
        },

        /**
         * Show tick or cross on saving options
         *
         * @param data
         */
        show_tick_on_save = function( data ){
            if( data.error ) {
                console.log( data.error );
                $('#save_result').html( data.error );
                $('.icon-cross').show();
            }else{
                $('.icon-tick').show();
            }
        },

        /**
         * Save options
         *
         * @param e
         * @param warn
         * @returns {boolean}
         */
        save_options = function( e, warn ){
            fetch( 'save_options', collect_options(), ( warn ? warn : show_tick_on_save ) );
            return noclick( e );
        },

        /**
         * Collect all options to save
         *
         * @returns {Array}
         */
        collect_options = function(){
            var data = [];
            $('form[name=mainform] section.active input[type=text]:not(:disabled)').each( function(){
               data.push({ name: this.name, value: this.value });
            });
            $('form[name=mainform] section.active input[type=hidden]').each( function(){
                data.push({ name: this.name, value: this.value });
            });
            $('form[name=mainform] section.active input[type=radio]:checked').each( function(){
                data.push({ name: this.name, value: this.value });
            });
            $('form[name=mainform] section.active input[type=checkbox]').each( function(){
                data.push({ name: this.name, value: ( this.checked ? this.value : '' ) });
            });
            $('form[name=mainform] section.active select').each( function(){
                data.push({ name: this.name, value: this.value });
            });
            return data;
        };


    return {

        init : function(){

            /**
             * Assign main events
             */
            $(document).ready(function(){

                assign_tabs();

                assign_domain_select();

                assign_domain_buts();

                add_first_domain();

                assign_main_events();

            });
        }

    }

})(jQuery);

__wtssms.init();