var tribe_tickets_tpp_admin={l10n:window.tribe_tickets_tpp_admin_strings||!1};!function(t,a,e){"use strict";a.checkmarkValidationMap=function(){return{email:function(t){return/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(t)},radio:function(t){return["yes","1",1,!0,"true","on","complete","completed"].includes(t.toLowerCase())}}},a.castStatusToBool=function(t){return(0,a.checkmarkValidationMap().radio)(t)},a.castBoolToStatus=function(t){return!0===t?"complete":"incomplete"},a.updatePayPalIpnStatus=function(){var e=t("#paypal-ipn-config-status"),i=t(".ipn-required");if(i){var n=_.reduce(i,function(a,e){return a&&!t(e).hasClass("no-checkmark")},!0),r=a.castBoolToStatus(n);e.text(a.l10n[r]).attr("data-status",r)}},a.isOkInput=function(e){var i=t(e).closest(".checkmark");if(i){var n=!1,r=a.checkmarkValidationMap();if(i.hasClass("tribe-field-email"))n=r.email(e.value);else if(i.hasClass("tribe-field-radio")){var c=t(e).closest(".tribe-field-wrap").find("input:checked").val();n=r.radio(c)}else n=!0;return n}},a.toggleCheckmark=function(){var e=a.isOkInput(this),i=t(this).closest(".checkmark");e?i.removeClass("no-checkmark"):i.addClass("no-checkmark"),a.updatePayPalIpnStatus()},a.setupValidationOnPanel=function(a,e){if(e.panel&&e.panel instanceof jQuery){var i=e.panel,n="Tribe__Tickets__Commerce__PayPal__Main"===i.data("default-provider"),r=!t("#ticket_id").val();n&&r&&t("#ticket_price, #ticket_sale_price").attr("data-required",!0).attr("data-validation-is-greater-than","0"),i.find(".tribe-validation").validation()}},a.init=function(){t(".checkmark input").each(function(){t(this).on("change",a.toggleCheckmark).each(a.toggleCheckmark)}),t("#event_tickets").on("after_panel_swap.tickets",a.setupValidationOnPanel)},t(function(){a.l10n&&a.init()})}(jQuery,tribe_tickets_tpp_admin,tribe_tickets_tpp_admin_strings);