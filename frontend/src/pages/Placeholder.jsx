export default function Placeholder({ title, phase }) {
  return (
    <>
      <h1 className="h3 mb-3">{title}</h1>
      <div className="panel">
        <p className="mb-0 text-muted">Ce module sera développé à la {phase}.</p>
      </div>
    </>
  );
}
