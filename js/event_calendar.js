(function (Drupal, drupalSettings) {
    Drupal.behaviors.eventCalendar = {
        attach: function (context, settings) {
            once('eventCalendar', '.event-calendar', context).forEach(function (calendar) {
                const updateCalendar = (month, year) => {
                    fetch(`/api-event/event-calendar/?month=${month}&year=${year}`)
                        .then(response => response.json())
                        .then(data => {
                            const calendarTbody = calendar.querySelector('.event-calendar__table tbody');
                            let html = '<tr>';
                            let count = 0;
                            data.calendar_days.forEach(day => {
                                if (count % 7 === 0 && count !== 0) {
                                    html += '</tr><tr>';
                                }
                               
                                // クラスを配列で構築
                                const classes = [
                                    day.day ? 'event-calendar__day' : 'event-calendar__empty',
                                    data.year + '-' + data.month + '-' + day.day == data.current_date ? 'event-calendar__current' : '',
                                    day.has_event ? 'event-calendar__has-event' : '',
                                ];
                                

                                html += `<td class="${classes.join(' ').trim()}">${day.day || ''}</td>`;
                                count++;
                            });
                            html += '</tr></tbody></table>';
                            calendarTbody.innerHTML = html;
                            calendar.querySelector('.event-calendar__title').textContent = `${data.year}年 ${data.month}月`;
                        });
                };

                calendar.querySelector('.event-calendar__button--prev').addEventListener('click', () => {
                    const month = parseInt(calendar.dataset.month) - 1 || 12;
                    const year = month === 12 ? parseInt(calendar.dataset.year) - 1 : parseInt(calendar.dataset.year);
                    calendar.dataset.month = month;
                    calendar.dataset.year = year;
                    updateCalendar(month, year);
                });

                calendar.querySelector('.event-calendar__button--next').addEventListener('click', () => {
                    const month = parseInt(calendar.dataset.month) + 1 > 12 ? 1 : parseInt(calendar.dataset.month) + 1;
                    const year = month === 1 ? parseInt(calendar.dataset.year) + 1 : parseInt(calendar.dataset.year);
                    calendar.dataset.month = month;
                    calendar.dataset.year = year;
                    updateCalendar(month, year);
                });
            });
        },
    };
})(Drupal, once);
