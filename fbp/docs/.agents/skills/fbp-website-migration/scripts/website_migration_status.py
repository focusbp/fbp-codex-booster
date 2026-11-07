#!/usr/bin/env python3
"""Evidence-linked migration progress. This journal never grants execution permission."""
import argparse
import json
from pathlib import Path

from migration_common import atomic_json,read_json,digest,now

STAGES=('scope','collection','implementation','test_import','backup','production_code',
        'production_import','dns','tls','routing','content','contact_delivery')
PUBLISHED=('production_code','production_import','dns','tls','routing','content')


def report(state):
    stages=state['stages'];stale=not Path(state['plan']).is_file() or digest(Path(state['plan']).read_bytes())!=state['manifest_sha256']
    stale_evidence=[]
    for name,stage in stages.items():
        evidence=stage.get('evidence')
        if evidence and (not Path(evidence['file']).is_file() or digest(Path(evidence['file']).read_bytes())!=evidence['sha256']):stale_evidence.append(name)
    done=lambda names:all(stages.get(s,{}).get('state') in ('verified','not_applicable') and s not in stale_evidence for s in names)
    return dict(schema='fbp.website.progress-report',version=1,appcode=state['appcode'],stale_plan=stale,
                published=not stale and done(PUBLISHED),migration_complete=not stale and done(STAGES),stale_evidence=stale_evidence,
                pending=[s for s in STAGES if stages.get(s,{}).get('state') not in ('verified','not_applicable') or s in stale_evidence],
                stages=stages,authorization='not_provided_by_this_journal')


def run(args):
    if Path(args.workspace).is_symlink():raise ValueError('Workspace cannot be a symlink')
    workspace=Path(args.workspace).resolve();workspace.mkdir(parents=True,exist_ok=True,mode=0o700)
    path=workspace/'progress.json'
    if args.action=='init':
        if path.exists():raise ValueError('Progress already exists; use show/record/replan')
        plan=Path(args.plan).resolve();read_json(plan,'fbp.website.migration')
        state=dict(schema='fbp.website.progress',version=1,appcode=args.appcode,plan=str(plan),
                   manifest_sha256=digest(plan.read_bytes()),created_at=now(),stages={},events=[])
    else:
        state=read_json(path,'fbp.website.progress')
        if args.action=='replan':
            plan=Path(args.plan).resolve();read_json(plan,'fbp.website.migration')
            state['events'].append(dict(action='replan',previous=state['manifest_sha256'],at=now()))
            state.update(plan=str(plan),manifest_sha256=digest(plan.read_bytes()),stages={})
        elif args.action=='record':
            if report(state)['stale_plan']:raise ValueError('Plan changed; replan and verify the affected work')
            if not args.note.strip():raise ValueError('A concise factual note is required')
            evidence=None
            if args.state=='verified':
                if not args.evidence:raise ValueError('Verified stages require an evidence file')
                file=Path(args.evidence).resolve()
                evidence=dict(file=str(file),sha256=digest(file.read_bytes()))
            elif args.evidence:
                file=Path(args.evidence).resolve();evidence=dict(file=str(file),sha256=digest(file.read_bytes()))
            event=dict(stage=args.stage,state=args.state,note=args.note,evidence=evidence,at=now(),manifest_sha256=state['manifest_sha256'])
            previous=state['stages'].get(args.stage)
            # Do not manufacture a new event for the same unchanged state/evidence.
            if not previous or any(previous.get(k)!=event.get(k) for k in ('state','note','evidence','manifest_sha256')):
                state['events'].append(event);state['stages'][args.stage]=event
    if args.action!='show':atomic_json(path,state)
    return report(state)


def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--workspace',required=True)
    sub=p.add_subparsers(dest='action',required=True)
    init=sub.add_parser('init');init.add_argument('--appcode',required=True);init.add_argument('--plan',required=True)
    sub.add_parser('show')
    replan=sub.add_parser('replan');replan.add_argument('--plan',required=True)
    record=sub.add_parser('record');record.add_argument('--stage',choices=STAGES,required=True)
    record.add_argument('--state',choices=['pending','failed','verified','not_applicable'],required=True)
    record.add_argument('--note',required=True);record.add_argument('--evidence')
    a=p.parse_args()
    try:print(json.dumps(run(a),ensure_ascii=False,indent=2));return 0
    except Exception as error:print(json.dumps({'ok':False,'error':type(error).__name__+': '+str(error)[:180]}));return 2


if __name__=='__main__':raise SystemExit(main())
