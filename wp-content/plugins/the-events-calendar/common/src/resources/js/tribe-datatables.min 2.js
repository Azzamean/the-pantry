window.tribe_data_table=null,function(e){"use strict";e.fn.tribeDataTable=function(t){e(document);var a=e.extend({language:{lengthMenu:tribe_l10n_datatables.length_menu,emptyTable:tribe_l10n_datatables.emptyTable,info:tribe_l10n_datatables.info,infoEmpty:tribe_l10n_datatables.info_empty,infoFiltered:tribe_l10n_datatables.info_filtered,zeroRecords:tribe_l10n_datatables.zero_records,search:tribe_l10n_datatables.search,paginate:{next:tribe_l10n_datatables.pagination.next,previous:tribe_l10n_datatables.pagination.previous},aria:{sortAscending:tribe_l10n_datatables.aria.sort_ascending,sortDescending:tribe_l10n_datatables.aria.sort_descending},select:{rows:{0:tribe_l10n_datatables.select.rows[0],_:tribe_l10n_datatables.select.rows._,1:tribe_l10n_datatables.select.rows[1]}}},lengthMenu:[[10,25,50,-1],[10,25,50,tribe_l10n_datatables.pagination.all]]},t),n=!1;this.is(".dataTable")&&(n=!0);var c={setVisibleCheckboxes:function(e,t,a){var n=e.find("thead"),l=e.find("tfoot"),o=n.find(".column-cb input:checkbox"),i=l.find(".column-cb input:checkbox");void 0===a&&(a=!1),e.find("tbody .check-column input:checkbox").prop("checked",a),o.prop("checked",a),i.prop("checked",a),a?(t.rows({page:"current"}).select(),c.addGlobalCheckboxLine(e,t)):(e.find(".tribe-datatables-all-pages-checkbox").remove(),t.rows().deselect())},addGlobalCheckboxLine:function(t,a){t.find(".tribe-datatables-all-pages-checkbox").remove();var n=t.find("thead"),l=t.find("tfoot"),o=(n.find(".column-cb input:checkbox"),l.find(".column-cb input:checkbox"),e("<a>").attr("href","#select-all").text(tribe_l10n_datatables.select_all_link)),i=e("<div>").css("text-align","center").text(tribe_l10n_datatables.all_selected_text).append(o),b=e("<th>").attr("colspan",a.columns()[0].length).append(i),s=e("<tr>").addClass("tribe-datatables-all-pages-checkbox").append(b);o.one("click",function(e){return a.rows().select(),o.text(tribe_l10n_datatables.clear_selection).one("click",function(){return c.setVisibleCheckboxes(t,a,!1),e.preventDefault(),!1}),e.preventDefault(),!1}),n.append(s)},togglePageCheckbox:function(e,t){var a=e.closest(".dataTable");c.setVisibleCheckboxes(a,t,e.is(":checked"))},toggleRowCheckbox:function(e,t){var a=e.closest("tr");e.is(":checked")?t.row(a).select():(t.row(a).deselect(),e.closest(".dataTable").find("thead .column-cb input:checkbox, tfoot .column-cb input:checkbox").prop("checked",!1))}};return this.each(function(){var t,l=e(this);t=n?l.DataTable():l.DataTable(a),window.tribe_data_table=t,void 0!==a.data&&(t.clear().draw(),t.rows.add(a.data),t.draw());var o=function(e,a){c.setVisibleCheckboxes(l,t,!1)};l.on({"order.dt":o,"search.dt":o,"length.dt":o}),l.on("click","thead .column-cb input:checkbox, tfoot .column-cb input:checkbox",function(){c.togglePageCheckbox(e(this),t)}),l.on("click","tbody .check-column input:checkbox",function(){c.toggleRowCheckbox(e(this),t)})})}}(jQuery);