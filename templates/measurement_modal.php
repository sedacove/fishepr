<?php
/**
 * Шаблон модального окна для замеров
 * 
 * @param string $modalId ID модального окна (по умолчанию 'measurementModal')
 */
$modalId = $modalId ?? 'measurementModal';
$formId = $formId ?? 'measurementForm';
$poolSelectId = $poolSelectId ?? 'measurementPool';
$datetimeFieldId = $datetimeFieldId ?? 'measurementDateTimeField';
$datetimeInputId = $datetimeInputId ?? 'measurementDateTime';
$temperatureId = $temperatureId ?? 'measurementTemperature';
$oxygenId = $oxygenId ?? 'measurementOxygen';
$currentPoolId = $currentPoolId ?? 'currentMeasurementPoolId';
$modalTitleId = $modalTitleId ?? 'measurementModalTitle';
$saveFunction = $saveFunction ?? 'saveMeasurement';
?>
<!-- Модальное окно для добавления/редактирования замера -->
<div class="modal fade" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="<?php echo htmlspecialchars($modalTitleId); ?>">Добавить замер</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="<?php echo htmlspecialchars($formId); ?>">
                    <input type="hidden" id="measurementId" name="id">
                    <input type="hidden" id="<?php echo htmlspecialchars($currentPoolId); ?>" name="pool_id">
                    
                    <div class="mb-3">
                        <label for="<?php echo htmlspecialchars($poolSelectId); ?>" class="form-label">Бассейн <span class="text-danger">*</span></label>
                        <select class="form-select" id="<?php echo htmlspecialchars($poolSelectId); ?>" name="pool_id" required>
                            <option value="">Выберите бассейн</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="<?php echo htmlspecialchars($datetimeFieldId); ?>" style="display: none;">
                        <label for="<?php echo htmlspecialchars($datetimeInputId); ?>" class="form-label">Дата и время замера <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="<?php echo htmlspecialchars($datetimeInputId); ?>" name="measured_at">
                        <div class="form-hint">Если оставить поле пустым, будет использовано текущее время.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="<?php echo htmlspecialchars($temperatureId); ?>" class="form-label">Температура (°C) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="<?php echo htmlspecialchars($temperatureId); ?>" name="temperature" step="0.01" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="<?php echo htmlspecialchars($oxygenId); ?>" class="form-label">Кислород (O2) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="<?php echo htmlspecialchars($oxygenId); ?>" name="oxygen" step="0.01" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="<?php echo htmlspecialchars($saveFunction); ?>()">Сохранить</button>
            </div>
        </div>
    </div>
</div>

