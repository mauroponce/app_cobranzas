const http = require('node:http');
const net = require('node:net');
const { spawn } = require('node:child_process');

const publicPort = 8081;
const metroPort = 8082;
const isolationHeaders = {
  'Cross-Origin-Embedder-Policy': 'credentialless',
  'Cross-Origin-Opener-Policy': 'same-origin',
};

const metro = spawn(
  process.execPath,
  [require.resolve('expo/bin/cli'), 'start', '--web', '--clear', '--port', String(metroPort)],
  {
    cwd: process.cwd(),
    env: process.env,
    stdio: 'inherit',
  },
);

const proxy = http.createServer((request, response) => {
  const upstream = http.request(
    {
      hostname: '127.0.0.1',
      port: metroPort,
      path: request.url,
      method: request.method,
      headers: request.headers,
    },
    (upstreamResponse) => {
      response.writeHead(upstreamResponse.statusCode ?? 502, {
        ...upstreamResponse.headers,
        ...isolationHeaders,
      });
      upstreamResponse.pipe(response);
    },
  );

  upstream.on('error', () => {
    if (!response.headersSent) {
      response.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8', ...isolationHeaders });
    }
    response.end('Expo todavía se está iniciando. Recargá la página en unos segundos.');
  });
  request.pipe(upstream);
});

proxy.on('upgrade', (request, socket, head) => {
  const upstream = net.connect(metroPort, '127.0.0.1', () => {
    const headers = [];
    for (let index = 0; index < request.rawHeaders.length; index += 2) {
      headers.push(`${request.rawHeaders[index]}: ${request.rawHeaders[index + 1]}`);
    }
    upstream.write(
      `${request.method} ${request.url} HTTP/${request.httpVersion}\r\n${headers.join('\r\n')}\r\n\r\n`,
    );
    if (head.length > 0) upstream.write(head);
    socket.pipe(upstream).pipe(socket);
  });

  upstream.on('error', () => socket.destroy());
});

proxy.listen(publicPort, '0.0.0.0', () => {
  console.log(`Expo Web disponible en http://localhost:${publicPort}`);
});

let shuttingDown = false;
function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  proxy.close();
  if (!metro.killed) metro.kill(signal);
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
metro.on('exit', (code, signal) => {
  proxy.close(() => process.exit(code ?? (signal ? 1 : 0)));
});

