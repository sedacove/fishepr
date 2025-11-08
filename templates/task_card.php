<script type="text/template" id="taskCardTemplate">
<div class="task-card {{card_class}}" data-task-id="{{task_id}}">
    <div class="task-card-header">
        <div class="form-check">
            <input class="form-check-input task-checkbox" type="checkbox" {{checkbox_checked}} onchange="toggleTaskComplete({{task_id}}, this.checked)">
            <label class="form-check-label task-title" onclick="viewTask({{task_id}})">
                {{task_title}}
            </label>
        </div>
        {{edit_button_html}}
    </div>
    {{description_html}}
    <div class="task-meta">
        <div class="task-assigned">
            <i class="bi bi-person"></i> {{assigned_name}}
        </div>
        <div class="task-due-date {{due_date_class}}">
            <i class="bi bi-calendar"></i> {{due_date_text}}
        </div>
    </div>
    {{progress_html}}
    {{actions_html}}
</div>
</script>

