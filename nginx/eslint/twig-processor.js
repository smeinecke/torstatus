// ESLint processor that makes @shadcn/lint understand Twig templates.
//
// The plugin's site readers parse JSX/Svelte/Vue ASTs. Twig is plain HTML, so
// we compile each template into a virtual JSX file: every class="..." attribute
// becomes `<i className="..."/>`. Virtual lines are padded 1:1 with source lines
// so diagnostics keep their real line numbers; postprocess() maps columns back
// to the attribute value in the Twig source.
//
// Twig interpolation maps onto the same "dynamic fragment" semantics the plugin
// uses for Svelte: {{ expr }} and {% tag %} inside class strings become ${0}
// in a template literal, so surrounding whitespace-separated classes are still
// checked while the dynamic fragment is treated as unverifiable (the same way
// `bg-${color}` is in JSX — see require-static-classes).

const ATTR_RE = /\bclass\s*=\s*(["'])([\s\S]*?)\1/g;
const TWIG_RE = /\{\{[\s\S]*?\}\}|\{%[\s\S]*?%\}/g;
const mapsByFile = new Map();

function lineStartsOf(text) {
  const starts = [0];
  for (let i = 0; i < text.length; i++) if (text[i] === '\n') starts.push(i + 1);
  return starts;
}

function toJsxExpression(raw) {
  const value = raw.replace(/[\r\n]+/g, ' ');
  if (!value.includes('{{') && !value.includes('{%')) {
    return { code: JSON.stringify(value), valueOffset: 0 }; // quoted literal
  }
  const tpl = '`' + value.replace(/[`\\]/g, '').replace(TWIG_RE, '${0}') + '`';
  return { code: `{${tpl}}`, valueOffset: 1 }; // expression container around template
}

export default {
  meta: { name: 'twig-class-processor', version: '1.0.0' },
  supportsAutofix: false,

  preprocess(text, filename) {
    const starts = lineStartsOf(text);
    const vLines = [];
    const lineMaps = new Map();
    let m;
    ATTR_RE.lastIndex = 0;
    while ((m = ATTR_RE.exec(text)) !== null) {
      const valueStart = m.index + m[0].length - m[2].length; // offset of first value char
      let line = 0;
      while (line + 1 < starts.length && starts[line + 1] <= m.index) line++;
      const { code, valueOffset } = toJsxExpression(m[2]);
      const piece = `<i className=${code}/>`;
      const base = vLines[line] ?? '';
      const vValueStart = base.length + '<i className='.length + valueOffset;
      const entry = {
        vStart: vValueStart,
        vEnd: vValueStart + code.length - valueOffset - (valueOffset ? 1 : 0),
        rStart: valueStart - starts[line],
      };
      vLines[line] = base + piece + ';';
      if (!lineMaps.has(line)) lineMaps.set(line, []);
      lineMaps.get(line).push(entry);
    }
    mapsByFile.set(filename, lineMaps);
    return [{ text: vLines.join('\n'), filename: `${filename}/0.jsx` }];
  },

  postprocess(messageLists, filename) {
    const lineMaps = mapsByFile.get(filename) ?? new Map();
    mapsByFile.delete(filename);
    const mapCol = (line, col) => {
      const segs = lineMaps.get(line - 1);
      if (!segs) return col;
      for (const seg of segs) {
        if (col >= seg.vStart && col <= seg.vEnd + 1) return seg.rStart + Math.max(0, col - seg.vStart);
      }
      return segs[0]?.rStart ?? col;
    };
    return messageLists.flat().map((msg) => ({
      ...msg,
      column: mapCol(msg.line, msg.column),
      endColumn: msg.endColumn != null ? mapCol(msg.endLine ?? msg.line, msg.endColumn) : msg.endColumn,
    }));
  },
};
