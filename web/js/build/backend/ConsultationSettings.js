define(["require","exports"],function(e,t){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var i=function(){var o=$("#consultationSettingsForm");$(".urlPathHolder .shower a").click(function(e){e.preventDefault(),$(".urlPathHolder .shower").addClass("hidden"),$(".urlPathHolder .holder").removeClass("hidden")}),o.submit(function(){var e,t=$("#tagsList").pillbox("items"),i=[],n=$('<input type="hidden" name="tags">');for(e=0;e<t.length;e++)void 0===t[e].id?i.push({id:0,name:t[e].text}):i.push({id:t[e].id,name:t[e].text});n.attr("value",JSON.stringify(i)),o.append(n)}),Sortable.create(document.getElementById("tagsListUl"),{draggable:".pill"});var t=$("#adminsMayEdit"),i=$("#iniatorsMayEdit").parents("label").first().parent();t.change(function(){if($(this).prop("checked"))i.removeClass("hidden");else{var e=__t("admin","adminMayEditConfirm");bootbox.confirm(e,function(e){e?(i.addClass("hidden"),i.find("input").prop("checked",!1)):t.prop("checked",!0)})}}),t.prop("checked")||i.addClass("hidden"),$("#singleMotionMode").change(function(){$(this).prop("checked")?$("#forceMotionRow").removeClass("hidden"):$("#forceMotionRow").addClass("hidden")}).change(),$('[data-toggle="tooltip"]').tooltip()};new(t.ConsultationSettings=i)});
//# sourceMappingURL=ConsultationSettings.js.map
