/* The textual summary remains useful if canvas or the live endpoint is unavailable. */
(() => {
  'use strict';
  const canvas = document.getElementById('project-chart');
  const description = document.getElementById('chart-description');
  if (!canvas || !description) return;
  const colors = ['#264f3c', '#9bb865', '#d5dfa9', '#d9dfd2'];
  let statuses = [];
  function draw() {
    const context = canvas.getContext('2d');
    if (!context) return;
    const width = canvas.clientWidth;
    if (!width) return;
    const height = 210;
    const scale = window.devicePixelRatio || 1;
    canvas.width = width * scale;
    canvas.height = height * scale;
    context.scale(scale, scale);
    const total = statuses.reduce((sum, item) => sum + item.count, 0);
    const radius = Math.min(width * 0.23, 70);
    const x = width < 280 ? width * 0.29 : width * 0.32;
    const y = 103;
    let start = -Math.PI / 2;
    const slices = total ? statuses : [{count: 1}];
    slices.forEach((item, index) => {
      const end = start + (item.count / (total || 1)) * Math.PI * 2;
      context.beginPath();
      context.arc(x, y, radius, start, end);
      context.strokeStyle = total ? colors[index % colors.length] : '#e1e7d9';
      context.lineWidth = 20;
      context.stroke();
      start = end;
    });
    context.textAlign = 'center';
    context.fillStyle = '#234c3b';
    context.font = 'bold 30px "Segoe UI", sans-serif';
    context.fillText(String(total), x, y + 3);
    context.font = '10px "Microsoft YaHei", sans-serif';
    context.fillStyle = '#819074';
    context.fillText('探索中的作品', x, y + 24);
    context.textAlign = 'left';
    statuses.forEach((item, index) => {
      const rowY = 64 + index * 27;
      const legendX = width * 0.65;
      context.fillStyle = colors[index % colors.length];
      context.fillRect(legendX, rowY - 7, 7, 7);
      context.fillStyle = '#6a795e';
      context.fillText(`${item.label}  ${item.count}`, legendX + 14, rowY);
    });
  }
  fetch('/?r=site/stats', {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
    .then(response => {
      if (!response.ok) throw new Error('Statistics unavailable');
      return response.json();
    })
    .then(data => {
      statuses = (Array.isArray(data.project_statuses) ? data.project_statuses : [])
        .filter(item => typeof item.label === 'string' && Number.isFinite(Number(item.count)))
        .map(item => ({label: item.label, count: Math.max(0, Number(item.count))}));
      description.textContent = statuses.length ? statuses.map(item => `${item.label} ${item.count} 个`).join(' · ') : '暂无项目数据';
      canvas.setAttribute('aria-label', `项目状态分布：${description.textContent}`);
      draw();
      window.addEventListener('resize', draw, {passive: true});
    })
    .catch(() => { description.textContent = '统计暂时不可用，欢迎前往项目页面查看作品。'; });
})();
