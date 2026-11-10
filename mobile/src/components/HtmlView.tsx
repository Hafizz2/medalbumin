import * as WebBrowser from 'expo-web-browser';
import { useMemo, useState } from 'react';
import { View } from 'react-native';
import { WebView } from 'react-native-webview';
import { useTheme } from '../theme';

/**
 * Renders official-note HTML (already sanitised by the server) with the app's
 * typography, grows to fit its content, and opens links in the browser.
 */
export function HtmlView({ html, dir = 'ltr', fontSize = 15 }: { html: string; dir?: 'ltr' | 'rtl'; fontSize?: number }) {
  const { c } = useTheme();
  const [height, setHeight] = useState(200);

  const doc = useMemo(
    () => `<!doctype html><html dir="${dir}"><head><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=4">
<style>
  :root { color-scheme: light dark; }
  html, body { margin: 0; padding: 0; background: transparent; }
  body { font-family: -apple-system, 'Segoe UI', Roboto, 'Noto Sans Ethiopic', sans-serif; font-size: ${fontSize}px; line-height: 1.6; color: ${c.text}; word-wrap: break-word; }
  p { margin: 0 0 12px; }
  h2 { font-size: 1.2em; margin: 18px 0 8px; letter-spacing: -0.01em; }
  h3 { font-size: 1.05em; margin: 14px 0 6px; }
  ul, ol { padding-${dir === 'rtl' ? 'right' : 'left'}: 22px; margin: 0 0 12px; }
  li { margin-bottom: 4px; }
  mark { background: ${c.tealSoft}; color: ${c.text}; border-radius: 4px; padding: 0 3px; }
  strong, b { font-weight: 600; }
  table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 12px 0; background: ${c.card}; border: 1px solid ${c.line}; border-radius: 16px; overflow: hidden; font-size: 0.93em; }
  th, td { padding: 10px 12px; text-align: ${dir === 'rtl' ? 'right' : 'left'}; vertical-align: top; border-top: 1px solid ${c.divider}; }
  tr:first-child th, tr:first-child td { border-top: 0; }
  th { background: ${c.chip2}; font-weight: 600; }
  td:first-child { font-family: ui-monospace, Menlo, monospace; color: ${c.teal}; white-space: nowrap; }
  img { max-width: 100%; border-radius: 12px; }
  a { color: ${c.teal}; }
  blockquote { margin: 0 0 12px; padding: 8px 14px; border-${dir === 'rtl' ? 'right' : 'left'}: 3px solid ${c.teal}; background: ${c.tealWash}; border-radius: 8px; }
</style></head><body>${html}
<script>
  function send(){ window.ReactNativeWebView.postMessage(String(document.documentElement.scrollHeight)); }
  window.addEventListener('load', send); new ResizeObserver(send).observe(document.body); setTimeout(send, 50);
</script></body></html>`,
    [html, dir, fontSize, c],
  );

  return (
    <View style={{ height }}>
      <WebView
        originWhitelist={['*']}
        source={{ html: doc }}
        style={{ backgroundColor: 'transparent' }}
        scrollEnabled={false}
        showsVerticalScrollIndicator={false}
        javaScriptEnabled
        onMessage={(e) => {
          const h = Number(e.nativeEvent.data);
          if (h > 0 && Math.abs(h - height) > 2) setHeight(h);
        }}
        onShouldStartLoadWithRequest={(req) => {
          if (req.url.startsWith('http')) {
            void WebBrowser.openBrowserAsync(req.url);
            return false;
          }
          return true;
        }}
      />
    </View>
  );
}
