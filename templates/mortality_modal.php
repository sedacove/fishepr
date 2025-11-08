<?php
/**
 * Шаблон модального окна для падежа
 * 
 * @param string $modalId ID модального окна (по умолчанию 'mortalityModal')
 */
$modalId = $modalId ?? 'mortalityModal';
$formId = $formId ?? 'mortalityForm';
$poolSelectId = $poolSelectId ?? 'mortalityPool';
$datetimeFieldId = $datetimeFieldId ?? 'mortalityDateTimeField';
$datetimeInputId = $datetimeInputId ?? 'mortalityDateTime';
$weightId = $weightId ?? 'mortalityWeight';
$fishCountId = $fishCountId ?? 'mortalityFishCount';
$currentPoolId = $currentPoolId ?? 'currentMortalityPoolId';
$modalTitleId = $modalTitleId ?? 'mortalityModalTitle';
$saveFunction = $saveFunction ?? 'saveMortality';
?>
<!-- Модальное окно для добавления/редактирования падежа -->
<div class="modal fade" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="<?php echo htmlspecialchars($modalTitleId); ?>">Зарегистрировать падеж</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="<?php echo htmlspecialchars($formId); ?>">
                    <input type="hidden" id="mortalityId" name="id">
                    <input type="hidden" id="<?php echo htmlspecialchars($currentPoolId); ?>" name="pool_id">
                    
                    <div class="mb-3">
                        <label for="<?php echo htmlspecialchars($poolSelectId); ?>" class="form-label">Бассейн <span class="text-danger">*</span></label>
                        <select class="form-select" id="<?php echo htmlspecialchars($poolSelectId); ?>" name="pool_id" required>
                            <option value="">Выберите бассейн</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="<?php echo htmlspecialchars($datetimeFieldId); ?>" style="display: none;">
                        <label for="<?php echo htmlspecialchars($datetimeInputId); ?>" class="form-label">Дата и время записи <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="<?php echo htmlspecialchars($datetimeInputId); ?>" name="recorded_at">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="<?php echo htmlspecialchars($weightId); ?>" class="form-label">Вес (кг) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="<?php echo htmlspecialchars($weightId); ?>" name="weight" step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="<?php echo htmlspecialchars($fishCountId); ?>" class="form-label">Количество рыб (шт) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="<?php echo htmlspecialchars($fishCountId); ?>" name="fish_count" min="0" required>
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

