<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>

<!-- Подключаем Vue 2 через CDN для чистоты эксперимента -->
<script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>

<div id="order-history-app" class="order-history-wrapper" style="max-width: 800px; margin: 20px auto; font-family: sans-serif;">
    <h2>История ваших заказов</h2>
    
    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Источник данных:</strong> 
                <span :style="{ color: source === 'redis' ? 'green' : 'blue' }">
                    {{ source === 'redis' ? 'Кэш (Redis)' : (source === 'database' ? 'Slave БД' : 'Загрузка...') }}
                </span>
            </div>
            
            <button @click="generateReport" :disabled="report.isLoading" 
                    style="padding: 8px 16px; background: #0063c6; color: white; border: none; border-radius: 4px; cursor: pointer;">
                {{ report.isLoading ? 'Генерация отчета...' : 'Скачать CSV отчет' }}
            </button>
        </div>

        <!-- Статус генерации отчета -->
        <div v-if="report.status" style="margin-top: 15px; padding: 10px; border-radius: 4px;" 
             :style="{ backgroundColor: report.status === 'error' ? '#fee' : '#efe' }">
            Статус задачи #{{ report.taskId }}: <b>{{ report.status }}</b>
            
            <a v-if="report.fileUrl" :href="'/bitrix/services/main/ajax.php?mode=class&c=app:order.history&action=downloadReport&taskId=' + report.taskId + '&sessid=' + sessid" 
               style="display: inline-block; margin-left: 15px; color: #0063c6; font-weight: bold;">
                💾 Сохранить файл
            </a>
        </div>
    </div>

    <!-- Таблица заказов -->
    <table v-if="orders.length > 0" style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead>
            <tr style="background: #f1f1f1;">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">ID Заказа</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Дата создания</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Статус</th>
                <th style="padding: 12px; text-align: right; border-bottom: 2px solid #ddd;">Сумма</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="order in orders" :key="order.ID" style="border-bottom: 1px solid #eee;">
                <td style="padding: 12px;"><b>{{ order.ID }}</b></td>
                <td style="padding: 12px;">{{ order.DATE_INSERT }}</td>
                <td style="padding: 12px;">{{ order.STATUS_ID }}</td>
                <td style="padding: 12px; text-align: right;">{{ parseFloat(order.PRICE).toLocaleString('ru-RU') }} ₽</td>
            </tr>
        </tbody>
    </table>
    
    <div v-else-if="!loading" style="padding: 20px; text-align: center; color: #666;">
        У вас пока нет заказов.
    </div>
</div>

<script>
new Vue({
    el: '#order-history-app',
    data: {
        loading: true,
        sessid: BX.bitrix_sessid(),
        orders: [],
        source: '',
        report: {
            isLoading: false,
            taskId: null,
            status: '',
            fileUrl: '',
            pollingInterval: null
        }
    },
    mounted() {
        this.fetchOrders();
    },
    methods: {
        // Запрос к микросервису через D7 API
        fetchOrders() {
            this.loading = true;
            BX.ajax.runComponentAction('app:order.history', 'getOrders', {
                mode: 'class'
            }).then(response => {
                if (response.data && response.data.data) {
                    this.orders = response.data.data;
                    this.source = response.data.source;
                }
                this.loading = false;
            }).catch(error => {
                console.error("Ошибка загрузки:", error);
                this.loading = false;
            });
        },

        // Инициация фоновой генерации отчета
        generateReport() {
            this.report.isLoading = true;
            this.report.status = 'Отправка задачи...';
            this.report.fileUrl = '';

            BX.ajax.runComponentAction('app:order.history', 'generateReport', {
                mode: 'class'
            }).then(response => {
                if (response.data.error) {
                    this.report.status = 'error';
                    alert(response.data.message);
                    this.report.isLoading = false;
                    return;
                }
                this.report.taskId = response.data.task_id;
                this.report.status = 'В очереди (pending)';
                this.startPolling();
            }).catch(error => {
                this.report.status = 'error';
                this.report.isLoading = false;
            });
        },

        // Поллинг статуса задачи каждые 2 секунды
        startPolling() {
            if (this.report.pollingInterval) {
                clearInterval(this.report.pollingInterval);
            }

            this.report.pollingInterval = setInterval(() => {
                BX.ajax.runComponentAction('app:order.history', 'checkReportStatus', {
                    mode: 'class',
                    data: { taskId: this.report.taskId }
                }).then(response => {
                    const data = response.data;
                    this.report.status = data.status;

                    // Если задача завершена
                    if (data.status === 'done') {
                        this.report.fileUrl = data.file_url;
                        this.stopPolling();
                    } else if (data.status === 'error') {
                        this.stopPolling();
                    }
                });
            }, 2000); // Опрос раз в 2 секунды
        },

        stopPolling() {
            clearInterval(this.report.pollingInterval);
            this.report.isLoading = false;
        }
    },
    beforeDestroy() {
        if (this.report.pollingInterval) {
            clearInterval(this.report.pollingInterval);
        }
    }
});
</script>